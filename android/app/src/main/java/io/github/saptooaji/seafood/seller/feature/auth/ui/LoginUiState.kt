package io.github.saptooaji.seafood.seller.feature.auth.ui

import android.content.Context
import io.github.saptooaji.seafood.seller.core.util.UiText

data class LoginUiState(
    val email: String = "",
    val password: String = "",
    val emailError: UiText? = null,
    val passwordError: UiText? = null,
    /** An error that belongs to no field: no connection, a server error, a 429, or Google. */
    val generalError: UiText? = null,
    /** Signing in with email and password. */
    val isSubmitting: Boolean = false,
    /** The Google account picker is open, or its ID token is being exchanged. */
    val isGoogleSubmitting: Boolean = false,
    /** The build has a Google server client ID, so the Google button shows. */
    val googleAvailable: Boolean = false,
    val loggedIn: Boolean = false,
    /** The seller arrived here because the session ended (a 401). */
    val sessionExpired: Boolean = false,
    /** Seconds left before another try is allowed after a 429; 0 when none. */
    val retryAfterSeconds: Long = 0,
) {
    /** Either button may start a sign-in: nothing is running and no 429 wait. */
    val canSubmit: Boolean get() = !isSubmitting && !isGoogleSubmitting && retryAfterSeconds <= 0
}

sealed interface LoginEvent {
    data class EmailChanged(
        val email: String,
    ) : LoginEvent

    data class PasswordChanged(
        val password: String,
    ) : LoginEvent

    data object Submit : LoginEvent

    /**
     * "Masuk dengan Google". Carries the activity Credential Manager shows its
     * picker over; not a data class, so the activity never ends up in a log.
     */
    class GoogleSignIn(
        val activityContext: Context,
    ) : LoginEvent
}
