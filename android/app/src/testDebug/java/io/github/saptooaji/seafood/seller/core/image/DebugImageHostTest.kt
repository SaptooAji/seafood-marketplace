package io.github.saptooaji.seafood.seller.core.image

import org.junit.Assert.assertEquals
import org.junit.Test

/**
 * In a debug build the server's photo URLs use its own `APP_URL`
 * (`http://localhost:8000`), which the emulator cannot reach; images are
 * fetched from the host the API is reached at instead.
 */
class DebugImageHostTest {
    private val apiBaseUrl = "http://10.0.2.2:8000/api/v1/"

    @Test
    fun aPhotoUrlTakesTheApiHost() {
        assertEquals(
            "http://10.0.2.2:8000/storage/products/7/a_thumb.webp",
            DebugImageHost.rewrite("http://localhost:8000/storage/products/7/a_thumb.webp", apiBaseUrl),
        )
    }

    @Test
    fun theSchemePortAndQueryFollowTheApi() {
        assertEquals(
            "https://api.example.test/storage/a.webp?v=2",
            DebugImageHost.rewrite("http://localhost:8000/storage/a.webp?v=2", "https://api.example.test/api/v1/"),
        )
    }

    @Test
    fun anythingThatIsNotAnHttpUrlIsLeftAlone() {
        assertEquals("not a url", DebugImageHost.rewrite("not a url", apiBaseUrl))
        assertEquals("content://media/1", DebugImageHost.rewrite("content://media/1", apiBaseUrl))
    }
}
