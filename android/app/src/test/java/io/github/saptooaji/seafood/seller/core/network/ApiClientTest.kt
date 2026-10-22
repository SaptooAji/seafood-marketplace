package io.github.saptooaji.seafood.seller.core.network

import app.cash.turbine.test
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.async
import kotlinx.coroutines.awaitAll
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.HttpUrl
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Url
import java.util.Collections

/**
 * The HTTP stack every feature uses (#230), against a local MockWebServer:
 * headers, error mapping, the session ending on a 401 (MOB-6), and what the
 * debug log may show (MOB-4).
 */
class ApiClientTest {
    private lateinit var server: MockWebServer
    private lateinit var baseUrl: HttpUrl
    private val tokenStore = FakeTokenStore()
    private val sessionEvents = SessionEvents()
    private val logLines: MutableList<String> = Collections.synchronizedList(mutableListOf())

    @BeforeEach
    fun startServer() {
        server = MockWebServer()
        server.start()
        baseUrl = server.url("/api/v1/")
    }

    @AfterEach
    fun stopServer() {
        server.close()
    }

    private fun api(debugLog: Boolean = false): TestApi {
        val client =
            HttpClients.okHttpClient(
                baseUrl = baseUrl,
                tokenStore = tokenStore,
                sessionEvents = sessionEvents,
                debugLogger = if (debugLog) { line -> logLines += line } else null,
            )
        return HttpClients.retrofit(baseUrl, client).create(TestApi::class.java)
    }

    private fun <T> call(block: suspend TestApi.() -> Response<T>): ApiResult<T> {
        val api = api()
        return runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.block() } } }
    }

    // --- Headers ----------------------------------------------------------

    @Test
    fun `a signed-in request carries the token and asks for JSON`() {
        tokenStore.state.value = "sfm_abc"
        server.enqueue(json(200, PRODUCTS))

        call { products() }

        val request = server.takeRequest()
        assertEquals("Bearer sfm_abc", request.headers["Authorization"])
        assertEquals("application/json", request.headers["Accept"])
    }

    @Test
    fun `without a token there is no Authorization header`() {
        server.enqueue(json(200, PRODUCTS))

        call { products() }

        assertNull(server.takeRequest().headers["Authorization"])
    }

    @Test
    fun `sign-in requests never carry the stored token`() {
        tokenStore.state.value = "sfm_abc"
        server.enqueue(json(200, """{"token":"sfm_new","user":{"id":1}}"""))

        call { login(LoginBody("budi@example.com", "rahasia123", "Pixel 9")) }

        val request = server.takeRequest()
        assertNull(request.headers["Authorization"])
        assertEquals("application/json", request.headers["Accept"])
    }

    @Test
    fun `a request to another host never carries the token`() {
        tokenStore.state.value = "sfm_abc"
        MockWebServer().use { other ->
            other.start()
            other.enqueue(json(200, "{}"))

            call { absolute(other.url("/steal").toString()) }

            assertNull(other.takeRequest().headers["Authorization"])
        }
    }

    // --- Results ----------------------------------------------------------

    @Test
    fun `a successful answer is parsed and unknown fields are ignored`() {
        server.enqueue(json(200, """{"data":[{"id":7,"name":"Kakap Merah","slug":"kakap-merah"}],"meta":{"total":1}}"""))

        val result = call { products() }

        assertEquals(ApiResult.Success(ProductsDto(listOf(ProductDto(7, "Kakap Merah")))), result)
    }

    @ParameterizedTest(name = "{0}")
    @MethodSource("errors")
    fun `errors map to ApiResult`(
        @Suppress("UNUSED_PARAMETER") case: String,
        response: MockResponse,
        expected: ApiResult<Nothing>,
    ) {
        server.enqueue(response)

        assertEquals(expected, call { products() })
    }

    @Test
    fun `no connection is a network error`() {
        val api = api()
        server.close()

        val result = runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.products() } } }

        assertEquals(ApiResult.NetworkError, result)
    }

    // --- Session ending on 401 (MOB-6) ------------------------------------

    @Test
    fun `a 401 on a signed-in request deletes the token and signs out`() =
        runBlocking {
            tokenStore.state.value = "sfm_expired"
            server.enqueue(json(401, """{"message":"Unauthenticated."}"""))

            sessionEvents.events.test {
                val result = call { products() }

                assertEquals(ApiResult.Unauthorized, result)
                assertEquals(SessionEvent.SignedOut, awaitItem())
                expectNoEvents()
            }
            assertNull(tokenStore.token().first())
        }

    @Test
    fun `a 401 on sign-in is an ordinary refusal and does not sign out`() =
        runBlocking {
            tokenStore.state.value = "sfm_abc"
            server.enqueue(json(401, """{"message":"Unauthenticated."}"""))

            sessionEvents.events.test {
                val result = call { login(LoginBody("budi@example.com", "salah", "Pixel 9")) }

                assertEquals(ApiResult.Unauthorized, result)
                expectNoEvents()
            }
            assertEquals("sfm_abc", tokenStore.token().first())
        }

    @Test
    fun `two requests failing with the same token sign out once`() =
        runBlocking {
            tokenStore.state.value = "sfm_expired"
            repeat(2) { server.enqueue(json(401, """{"message":"Unauthenticated."}""")) }
            val api = api()

            sessionEvents.events.test {
                withContext(Dispatchers.IO) {
                    List(2) { async { apiCall(HttpClients.json) { api.products() } } }.awaitAll()
                }

                assertEquals(SessionEvent.SignedOut, awaitItem())
                expectNoEvents()
            }
        }

    // --- Debug log (MOB-4) ------------------------------------------------

    @Test
    fun `the debug log masks the token, passwords, and the token in a sign-in answer`() {
        tokenStore.state.value = "sfm_abc"
        server.enqueue(json(200, """{"token":"sfm_secret_new","user":{"id":1}}"""))
        server.enqueue(json(200, PRODUCTS))
        val api = api(debugLog = true)

        runBlocking {
            withContext(Dispatchers.IO) {
                api.login(LoginBody("budi@example.com", "rahasia123", "Pixel 9"))
                api.products()
            }
        }

        val log = logLines.joinToString("\n")
        assertTrue(log.contains("Authorization: ██"), log)
        assertFalse(log.contains("sfm_abc"), "the stored token must not be logged")
        assertFalse(log.contains("rahasia123"), "the password must not be logged")
        assertFalse(log.contains("sfm_secret_new"), "the token in the answer must not be logged")
    }

    @Test
    fun `nothing is logged when there is no debug logger`() {
        tokenStore.state.value = "sfm_abc"
        server.enqueue(json(200, PRODUCTS))

        call { products() }

        assertTrue(logLines.isEmpty())
    }

    companion object {
        private const val PRODUCTS = """{"data":[]}"""

        private fun json(
            code: Int,
            body: String,
        ): MockResponse =
            MockResponse
                .Builder()
                .code(code)
                .addHeader("Content-Type", "application/json")
                .body(body)
                .build()

        @JvmStatic
        fun errors(): List<Arguments> =
            listOf(
                arguments(
                    "422 with field errors",
                    json(
                        422,
                        """
                        {"message":"Nama wajib diisi.",
                         "errors":{"name":["Nama wajib diisi."],"variants.0.price":["Harga minimal 1."]}}
                        """.trimIndent(),
                    ),
                    ApiResult.Invalid(
                        mapOf("name" to listOf("Nama wajib diisi."), "variants.0.price" to listOf("Harga minimal 1.")),
                        "Nama wajib diisi.",
                    ),
                ),
                arguments(
                    "403 suspended store, message shown as is",
                    json(403, """{"message":"Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali."}"""),
                    ApiResult.Forbidden("Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali."),
                ),
                arguments("404", json(404, """{"message":"Not Found"}"""), ApiResult.NotFound),
                arguments(
                    "409",
                    json(409, """{"message":"Anda sudah punya toko."}"""),
                    ApiResult.Conflict("Anda sudah punya toko."),
                ),
                arguments(
                    "429 with Retry-After",
                    MockResponse
                        .Builder()
                        .code(429)
                        .addHeader("Retry-After", "30")
                        .body("""{"message":"Too Many Attempts."}""")
                        .build(),
                    ApiResult.TooManyRequests(30),
                ),
                arguments("500", json(500, """{"message":"Server Error"}"""), ApiResult.ServerError),
                arguments(
                    "502 with an HTML page from a proxy",
                    MockResponse
                        .Builder()
                        .code(502)
                        .addHeader("Content-Type", "text/html")
                        .body("<html><body>Bad Gateway</body></html>")
                        .build(),
                    ApiResult.ServerError,
                ),
                arguments("200 with a body that is not JSON", json(200, "<html>oops</html>"), ApiResult.ServerError),
            )
    }
}

@Serializable
data class ProductsDto(
    val data: List<ProductDto>,
)

@Serializable
data class ProductDto(
    val id: Int,
    val name: String,
)

@Serializable
data class LoginBody(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
)

@Serializable
data class TokenDto(
    val token: String,
)

interface TestApi {
    @GET("seller/products")
    suspend fun products(): Response<ProductsDto>

    @POST("auth/login")
    suspend fun login(
        @Body body: LoginBody,
    ): Response<TokenDto>

    @GET
    suspend fun absolute(
        @Url url: String,
    ): Response<Unit>
}

class FakeTokenStore : TokenStore {
    val state = MutableStateFlow<String?>(null)

    override fun token() = state

    override suspend fun save(token: String) {
        state.value = token
    }

    override suspend fun clear() {
        state.value = null
    }
}
