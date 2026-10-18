package io.github.saptooaji.seafood.seller.feature.auth.ui

import io.github.saptooaji.seafood.seller.core.util.UiText

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val emailError: UiText? = null,
    val passwordError: UiText? = null,
    /** An error that belongs to no field: no connection, a server error, or a 429. */
    val generalError: UiText? = null,
    val isSubmitting: Boolean = false,
    val loggedIn: Boolean = false,
    /** The seller arrived here because the session ended (a 401). */
    val sessionExpired: Boolean = false,
    /** Seconds left before another try is allowed after a 429; 0 when none. */
    val retryAfterSeconds: Long = 0,
) {
    val canSubmit: Boolean get() = !isSubmitting && retryAfterSeconds <= 0
}

sealed interface LoginEvent {
    data class EmailChanged(
        val email: String,
    ) : LoginEvent

    data class PasswordChanged(
        val password: String,
    ) : LoginEvent

    data object Submit : LoginEvent
}
