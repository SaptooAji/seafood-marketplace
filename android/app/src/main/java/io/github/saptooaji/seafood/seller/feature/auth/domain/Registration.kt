package io.github.saptooaji.seafood.seller.feature.auth.domain

/** What a new seller fills in to create an account (`POST auth/register`). */
data class Registration(
    val name: String,
    val email: String,
    val phone: String,
    val password: String,
    val passwordConfirmation: String,
) {
    /** Masks both passwords, so a log line or a crash report never shows them (MOB-4). */
    override fun toString(): String = "Registration(name=$name, email=$email, phone=$phone, password=██, passwordConfirmation=██)"
}
