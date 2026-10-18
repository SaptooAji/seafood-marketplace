package io.github.saptooaji.seafood.seller.core.network

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.assertThrows
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.CsvSource
import org.junit.jupiter.params.provider.ValueSource

class ApiBaseUrlTest {
    @ParameterizedTest(name = "{0} -> {1}")
    @CsvSource(
        "http://10.0.2.2:8000/api/v1/, http://10.0.2.2:8000/api/v1/",
        "http://10.0.2.2:8000/api/v1, http://10.0.2.2:8000/api/v1/",
        "http://localhost:8000, http://localhost:8000/api/v1/",
        "http://localhost:8000/, http://localhost:8000/api/v1/",
        "https://seafood.example/api/v1//, https://seafood.example/api/v1/",
        "'  http://localhost:8000/api/v1  ', http://localhost:8000/api/v1/",
    )
    fun `normalizes the configured URL to end with api v1 and a slash`(
        configured: String,
        expected: String,
    ) {
        assertEquals(expected, ApiBaseUrl.normalize(configured))
    }

    @ParameterizedTest
    @ValueSource(strings = ["", "   ", "10.0.2.2:8000", "ftp://10.0.2.2/api/v1/"])
    fun `rejects a URL that is blank or not http or https`(configured: String) {
        assertThrows<IllegalArgumentException> { ApiBaseUrl.normalize(configured) }
    }

    @Test
    fun `the URL the app uses ends with api v1 and a slash`() {
        assertTrue(ApiBaseUrl.value.endsWith("/api/v1/"), ApiBaseUrl.value)
    }
}
