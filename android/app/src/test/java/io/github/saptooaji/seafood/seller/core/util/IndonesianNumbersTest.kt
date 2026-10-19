package io.github.saptooaji.seafood.seller.core.util

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.CsvSource
import java.math.BigDecimal

/** Numbers written the Indonesian way, shared by every screen. */
class IndonesianNumbersTest {
    @ParameterizedTest(name = "{0} -> {1}")
    @CsvSource(
        "0, Rp0",
        "500, Rp500",
        "45000, Rp45.000",
        "1250000, Rp1.250.000",
        "185000, Rp185.000",
    )
    fun `rupiah have dots between thousands and no decimals`(
        amount: Long,
        expected: String,
    ) {
        assertEquals(expected, IndonesianNumbers.rupiah(amount))
    }

    @ParameterizedTest(name = "{0} -> {1}")
    @CsvSource(
        "1.500, '1,5'",
        "2.000, 2",
        "0.250, '0,25'",
        "18.500, '18,5'",
        "1250.750, '1.250,75'",
        "0.000, 0",
    )
    fun `decimals from the API drop trailing zeros and use a comma`(
        value: String,
        expected: String,
    ) {
        assertEquals(expected, IndonesianNumbers.decimal(BigDecimal(value)))
    }
}
