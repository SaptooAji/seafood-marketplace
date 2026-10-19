package io.github.saptooaji.seafood.seller.core.util

/** An Indonesian mobile number, the same rule as `IndonesianPhone` on the server. */
object PhoneNumber {
    private val PATTERN = Regex("^(?:08|\\+628)\\d{8,13}$")
    private val SEPARATORS = Regex("[\\s-]")

    /** "0812 3456 7890" becomes "081234567890". */
    fun normalize(phone: String): String = phone.replace(SEPARATORS, "")

    /** @param phone already normalised */
    fun isValid(phone: String): Boolean = PATTERN.matches(phone)
}
