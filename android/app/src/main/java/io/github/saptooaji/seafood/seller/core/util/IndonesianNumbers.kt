package io.github.saptooaji.seafood.seller.core.util

import java.math.BigDecimal
import java.text.DecimalFormat
import java.text.DecimalFormatSymbols

/**
 * Numbers written the Indonesian way, the same on every screen: a dot
 * between thousands and a comma before decimals. Money is whole rupiah
 * (`Rp45.000`); weights and quantities come from the API as `decimal(10,3)`
 * strings and lose their trailing zeros (`"1.500"` is `1,5`).
 */
object IndonesianNumbers {
    private val symbols =
        DecimalFormatSymbols().apply {
            groupingSeparator = '.'
            decimalSeparator = ','
        }

    /** `45000` is `Rp45.000`. */
    fun rupiah(amount: Long): String = "Rp" + DecimalFormat("#,##0", symbols).format(amount)

    /** `1.500` is `1,5`, `2.000` is `2`, `1250.750` is `1.250,75`. */
    fun decimal(value: BigDecimal): String = DecimalFormat("#,##0.###", symbols).format(value)
}
