package io.github.saptooaji.seafood.seller.core.network

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test

/** A 503 keeps the server's message (#240), and is still the server's trouble, not the phone's. */
class ServiceUnavailableTest {
    private val unavailable = ApiResult.ServiceUnavailable("Saran deskripsi sedang tidak tersedia.")

    @Test
    fun `a 503 passes through map with its message`() {
        assertEquals(unavailable, unavailable.map { it })
    }

    @Test
    fun `a 503 is a server trouble`() {
        assertEquals(ConnectionTrouble.Server, ConnectionTrouble.of(unavailable))
    }
}
