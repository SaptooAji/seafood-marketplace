package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockWebServer
import okhttp3.OkHttpClient
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.assertThrows
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource
import java.io.IOException
import java.net.ConnectException
import java.net.SocketTimeoutException
import java.net.UnknownHostException

/**
 * A request that fails without an answer (#277): a server outage when the
 * phone is online, no connection when it is not.
 */
class ServerUnreachableTest {
    private lateinit var server: MockWebServer

    @BeforeEach
    fun startServer() {
        server = MockWebServer()
        server.start()
    }

    @AfterEach
    fun stopServer() {
        server.close()
    }

    /** The app's own HTTP stack against a server that has been shut down. */
    private fun callStoppedServer(deviceNetwork: DeviceNetwork): ApiResult<ProductsDto> {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore(), SessionEvents(), debugLogger = null, deviceNetwork = deviceNetwork)
        val api = HttpClients.retrofit(baseUrl, client).create(TestApi::class.java)
        server.close()
        return runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.products() } } }
    }

    /** A stack whose request fails with [failure] after the reachability check. */
    private fun callFailing(
        failure: IOException,
        deviceNetwork: DeviceNetwork,
    ): ApiResult<ProductsDto> {
        val baseUrl = server.url("/api/v1/")
        val client =
            OkHttpClient
                .Builder()
                .addInterceptor(ReachabilityInterceptor(deviceNetwork))
                .addInterceptor { throw failure }
                .build()
        val api = HttpClients.retrofit(baseUrl, client).create(TestApi::class.java)
        return runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.products() } } }
    }

    @Test
    fun `a stopped server while the phone is online is a server outage`() {
        assertEquals(ApiResult.ServerUnreachable, callStoppedServer(deviceNetwork = { true }))
    }

    @Test
    fun `a stopped server while the phone is offline is no connection`() {
        assertEquals(ApiResult.NetworkError, callStoppedServer(deviceNetwork = { false }))
    }

    @ParameterizedTest(name = "{0}")
    @MethodSource("failures")
    fun `a failure without an answer depends on the phone's network`(
        case: String,
        failure: IOException,
        online: Boolean,
        expected: ApiResult<Nothing>,
    ) {
        assertEquals(expected, callFailing(failure, deviceNetwork = { online }), case)
    }

    @Test
    fun `the phone's network is only asked when a request fails`() {
        var asked = 0
        server.enqueue(
            mockwebserver3.MockResponse
                .Builder()
                .code(200)
                .body("""{"data":[]}""")
                .build(),
        )
        val baseUrl = server.url("/api/v1/")
        val client =
            HttpClients.okHttpClient(baseUrl, FakeTokenStore(), SessionEvents(), debugLogger = null, deviceNetwork = {
                asked++
                true
            })
        val api = HttpClients.retrofit(baseUrl, client).create(TestApi::class.java)

        val result = runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.products() } } }

        assertEquals(ApiResult.Success(ProductsDto(emptyList())), result)
        assertEquals(0, asked)
    }

    @Test
    fun `a cancelled call stays cancelled`() {
        assertThrows<CancellationException> {
            runBlocking { apiCall<Unit>(HttpClients.json) { throw CancellationException("left the screen") } }
        }
    }

    @Test
    fun `a server outage passes through map unchanged`() {
        assertEquals(ApiResult.ServerUnreachable, ApiResult.ServerUnreachable.map { it })
    }

    companion object {
        @JvmStatic
        fun failures(): List<Arguments> =
            listOf(
                arguments("refused, online", ConnectException("Failed to connect"), true, ApiResult.ServerUnreachable),
                arguments("timed out, online", SocketTimeoutException("timeout"), true, ApiResult.ServerUnreachable),
                arguments("unknown host, online", UnknownHostException("api.example.test"), true, ApiResult.ServerUnreachable),
                arguments("refused, offline", ConnectException("Failed to connect"), false, ApiResult.NetworkError),
                arguments("timed out, offline", SocketTimeoutException("timeout"), false, ApiResult.NetworkError),
                arguments("unknown host, offline", UnknownHostException("api.example.test"), false, ApiResult.NetworkError),
            )
    }
}
