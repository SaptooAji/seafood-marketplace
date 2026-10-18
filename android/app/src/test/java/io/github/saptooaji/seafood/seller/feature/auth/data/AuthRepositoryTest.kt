package io.github.saptooaji.seafood.seller.feature.auth.data

import app.cash.turbine.test
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.auth.domain.Registration
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test

/** `AuthRepositoryImpl` against a MockWebServer speaking the real API's JSON. */
class AuthRepositoryTest {
    private lateinit var server: MockWebServer
    private val tokenStore = FakeTokenStore()
    private val sessionEvents = SessionEvents()
    private lateinit var repository: AuthRepositoryImpl

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger = null)
        val api = HttpClients.retrofit(baseUrl, client).create(AuthApi::class.java)
        repository = AuthRepositoryImpl(api, HttpClients.json, tokenStore, deviceName = "Google Pixel 9")
    }

    @AfterEach
    fun stop() {
        server.close()
    }

    private fun json(
        code: Int,
        body: String,
    ) = MockResponse
        .Builder()
        .code(code)
        .addHeader("Content-Type", "application/json")
        .body(body)
        .build()

    private fun <T> io(block: suspend () -> T): T = runBlocking { withContext(Dispatchers.IO) { block() } }

    @Test
    fun `signing in stores the token and returns the user`() {
        server.enqueue(
            json(
                200,
                """{"token":"sfm_abc","user":$BUDI_JSON}""",
            ),
        )

        val result = io { repository.login("budi@example.com", "rahasia123") }

        assertEquals(ApiResult.Success(User(7, "Budi Santoso", "budi@example.com", "081234567890", null)), result)
        assertEquals("sfm_abc", io { tokenStore.token().first() })
    }

    @Test
    fun `signing in sends the email, the password, and the device name`() {
        server.enqueue(json(200, """{"token":"sfm_abc","user":{"id":7,"name":"Budi","email":"budi@example.com"}}"""))

        io { repository.login("budi@example.com", "rahasia123") }

        val request = server.takeRequest()
        assertEquals("/api/v1/auth/login", request.url.encodedPath)
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("budi@example.com", body["email"]!!.jsonPrimitive.content)
        assertEquals("rahasia123", body["password"]!!.jsonPrimitive.content)
        assertEquals("Google Pixel 9", body["device_name"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a refused sign-in stores no token`() {
        server.enqueue(
            json(
                422,
                """{"message":"$REFUSED","errors":{"email":["$REFUSED"]}}""",
            ),
        )

        val result = io { repository.login("budi@example.com", "salah") }

        assertTrue(result is ApiResult.Invalid)
        assertNull(io { tokenStore.token().first() })
    }

    private val registration = Registration("Budi Santoso", "budi@example.com", "081234567890", "rahasia123", "rahasia123")

    @Test
    fun `registering stores the token and returns the new user`() {
        server.enqueue(json(201, """{"token":"sfm_new","user":$BUDI_JSON}"""))

        val result = io { repository.register(registration) }

        assertEquals(ApiResult.Success(User(7, "Budi Santoso", "budi@example.com", "081234567890", null)), result)
        assertEquals("sfm_new", io { tokenStore.token().first() })
    }

    @Test
    fun `registering sends every field, the confirmation, and the device name`() {
        server.enqueue(json(201, """{"token":"sfm_new","user":$BUDI_JSON}"""))

        io { repository.register(registration) }

        val request = server.takeRequest()
        assertEquals("/api/v1/auth/register", request.url.encodedPath)
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("Budi Santoso", body["name"]!!.jsonPrimitive.content)
        assertEquals("budi@example.com", body["email"]!!.jsonPrimitive.content)
        assertEquals("081234567890", body["phone"]!!.jsonPrimitive.content)
        assertEquals("rahasia123", body["password"]!!.jsonPrimitive.content)
        assertEquals("rahasia123", body["password_confirmation"]!!.jsonPrimitive.content)
        assertEquals("Google Pixel 9", body["device_name"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a refused registration stores no token`() {
        server.enqueue(json(422, """{"message":"$TAKEN","errors":{"email":["$TAKEN"]}}"""))

        val result = io { repository.register(registration) }

        assertEquals(ApiResult.Invalid(mapOf("email" to listOf(TAKEN)), TAKEN), result)
        assertNull(io { tokenStore.token().first() })
    }

    @Test
    fun `me reads the user from the data wrapper`() {
        tokenStore.state.value = "sfm_abc"
        server.enqueue(
            json(
                200,
                """{"data":$BUDI_WITH_AVATAR_JSON}""",
            ),
        )

        val result = io { repository.me() }

        assertEquals(ApiResult.Success(User(7, "Budi Santoso", "budi@example.com", "081234567890", "https://example.com/b.jpg")), result)
        assertEquals("Bearer sfm_abc", server.takeRequest().headers["Authorization"])
    }

    @Test
    fun `an expired token on me deletes the token and signs out`() =
        runBlocking {
            tokenStore.state.value = "sfm_expired"
            server.enqueue(json(401, """{"message":"Unauthenticated."}"""))

            sessionEvents.events.test {
                val result = io { repository.me() }

                assertEquals(ApiResult.Unauthorized, result)
                assertEquals(SessionEvent.SignedOut, awaitItem())
            }
            assertNull(tokenStore.token().first())
        }
}

private const val BUDI_FIELDS = """"id":7,"name":"Budi Santoso","email":"budi@example.com","phone":"081234567890""""
private const val BUDI_JSON = """{$BUDI_FIELDS,"avatar":null}"""
private const val BUDI_WITH_AVATAR_JSON = """{$BUDI_FIELDS,"avatar":"https://example.com/b.jpg"}"""

// The server's message under APP_LOCALE=en; the app shows its own text instead (AUTH-3).
private const val REFUSED = "These credentials do not match our records."
private const val TAKEN = "The email has already been taken."
