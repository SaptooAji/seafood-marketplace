package io.github.saptooaji.seafood.seller.core.network

import app.cash.turbine.test
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.HttpUrl
import okhttp3.Request
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test

/**
 * A suspended store is refused with a 403 by every seller endpoint
 * (`EnsureStoreIsNotSuspended`, AC-7). Wherever it happens, the app is told
 * once so it can show the store status, and the seller stays signed in.
 */
class StoreSuspendedTest {
    private lateinit var server: MockWebServer
    private lateinit var baseUrl: HttpUrl
    private val tokenStore = FakeTokenStore().apply { state.value = "sfm_abc" }
    private val sessionEvents = SessionEvents()

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
        baseUrl = server.url("/api/v1/")
    }

    @AfterEach
    fun stop() {
        server.close()
    }

    private fun get(path: String) {
        val client = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger = null)
        runBlocking {
            withContext(Dispatchers.IO) {
                client.newCall(Request.Builder().url(baseUrl.resolve(path)!!).build()).execute().close()
            }
        }
    }

    private fun forbidden(message: String) =
        MockResponse
            .Builder()
            .code(403)
            .addHeader("Content-Type", "application/json")
            .body("""{"message":"$message"}""")
            .build()

    @Test
    fun `a 403 from a seller endpoint reports the suspension with the server's message`() =
        runBlocking {
            server.enqueue(forbidden(SUSPENDED))

            sessionEvents.events.test {
                get("seller/products")

                assertEquals(SessionEvent.StoreSuspended(SUSPENDED), awaitItem())
            }
            // A suspension is not a sign-out.
            assertEquals("sfm_abc", tokenStore.state.value)
        }

    @Test
    fun `a 403 outside the seller endpoints is left to the screen`() =
        runBlocking {
            server.enqueue(forbidden("This action is unauthorized."))

            sessionEvents.events.test {
                get("me")

                expectNoEvents()
            }
        }

    @Test
    fun `a 403 from another host is ignored`() =
        runBlocking {
            MockWebServer().use { other ->
                other.start()
                other.enqueue(forbidden(SUSPENDED))
                val client = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger = null)

                sessionEvents.events.test {
                    withContext(Dispatchers.IO) {
                        client.newCall(Request.Builder().url(other.url("/api/v1/seller/products")).build()).execute().close()
                    }

                    expectNoEvents()
                }
            }
        }

    private companion object {
        const val SUSPENDED = "Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali."
    }
}
