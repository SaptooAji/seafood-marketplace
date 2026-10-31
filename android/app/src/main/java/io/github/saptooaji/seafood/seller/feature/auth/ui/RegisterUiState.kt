package io.github.saptooaji.seafood.seller.feature.auth.ui

import io.github.saptooaji.seafood.seller.core.util.UiText

data class RegisterUiState(
    val name: String = "",
    val email: String = "",
    val phone: String = "",
    val password: String = "",
    val passwordConfirmation: String = "",
    val nameError: UiText? = null,
    val emailError: UiText? = null,
    val phoneError: UiText? = null,
    val passwordError: UiText? = null,
    val passwordConfirmationError: UiText? = null,
    /** An error that belongs to no field: no connection, a server error, or a 429. */
    val generalError: UiText? = null,
    val isSubmitting: Boolean = false,
    /** The account exists and is signed in; the screen moves on to opening a store. */
    val registered: Boolean = false,
    /** Seconds left before another try is allowed after a 429; 0 when none. */
    val retryAfterSeconds: Long = 0,
) {
    val canSubmit: Boolean get() = !isSubmitting && retryAfterSeconds <= 0

    // The passwords stay out of logs and crash reports (MOB-4).
    override fun toString() =
        "RegisterUiState(name=$name, email=$email, phone=$phone, password=██, passwordConfirmation=██, " +
            "nameError=$nameError, emailError=$emailError, phoneError=$phoneError, passwordError=$passwordError, " +
            "passwordConfirmationError=$passwordConfirmationError, generalError=$generalError, isSubmitting=$isSubmitting, " +
            "registered=$registered, retryAfterSeconds=$retryAfterSeconds)"
}

sealed interface RegisterEvent {
    data class NameChanged(
        val name: String,
    ) : RegisterEvent

    data class EmailChanged(
        val email: String,
    ) : RegisterEvent

    data class PhoneChanged(
        val phone: String,
    ) : RegisterEvent

    data class PasswordChanged(
        val password: String,
    ) : RegisterEvent {
        override fun toString() = "PasswordChanged(password=██)"
    }

    data class PasswordConfirmationChanged(
        val passwordConfirmation: String,
    ) : RegisterEvent {
        override fun toString() = "PasswordConfirmationChanged(passwordConfirmation=██)"
    }

    data object Submit : RegisterEvent
}

/** Test tags of the registration screen. */
object RegisterTags {
    const val BACK = "register_back"
    const val NAME = "register_name"
    const val EMAIL = "register_email"
    const val PHONE = "register_phone"
    const val PASSWORD = "register_password"
    const val PASSWORD_CONFIRMATION = "register_password_confirmation"
    const val SUBMIT = "register_submit"
    const val LOGIN = "register_login"
}
