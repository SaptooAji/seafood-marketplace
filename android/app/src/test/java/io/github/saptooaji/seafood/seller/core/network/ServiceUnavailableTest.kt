package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test

/**
 * A 503: a feature that is off or failing upstream (#240). It keeps the
 * server's message, and is still the server's trouble, not the phone's.
 */
class ServiceUnavailableTest {
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

    private fun call(): ApiResult<ProductsDto> {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore(), SessionEvents(), debugLogger = null)
        val api = HttpClients.retrofit(baseUrl, client).create(TestApi::class.java)
        return runBlocking { withContext(Dispatchers.IO) { apiCall(HttpClients.json) { api.products() } } }
    }

    private val unavailable = ApiResult.ServiceUnavailable("Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti.")

    @Test
    fun `a 503 keeps the server's message`() {
        server.enqueue(
            MockResponse
                .Builder()
                .code(503)
                .addHeader("Content-Type", "application/json")
                .body("""{"message":"Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti."}""")
                .build(),
        )

        assertEquals(unavailable, call())
    }

    @Test
    fun `a 503 from a proxy without JSON has no message`() {
        server.enqueue(
            MockResponse
                .Builder()
                .code(503)
                .addHeader("Content-Type", "text/html")
                .body("<html><body>Service Unavailable</body></html>")
                .build(),
        )

        assertEquals(ApiResult.ServiceUnavailable(null), call())
    }

    @Test
    fun `a 503 passes through map with its message`() {
        assertEquals(unavailable, unavailable.map { it })
    }

    @Test
    fun `a 503 is a server trouble`() {
        assertEquals(ConnectionTrouble.Server, ConnectionTrouble.of(unavailable))
    }
}
