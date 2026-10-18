package io.github.saptooaji.seafood.seller.core.util

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.CsvSource

class DeviceNameTest {
    @ParameterizedTest(name = "{0} + {1} -> {2}")
    @CsvSource(
        "Google, Pixel 9, Google Pixel 9",
        "samsung, SM-A546E, Samsung SM-A546E",
        "Xiaomi, Xiaomi 14, Xiaomi 14",
        "'', Pixel 9, Pixel 9",
        "Google, '', Google",
        "'', '', Android",
    )
    fun `combines the maker and the model once`(
        manufacturer: String,
        model: String,
        expected: String,
    ) {
        assertEquals(expected, DeviceName.of(manufacturer, model))
    }

    @Test
    fun `stays within the 255 characters the API accepts`() {
        assertEquals(255, DeviceName.of("Maker", "M".repeat(400)).length)
    }
}
