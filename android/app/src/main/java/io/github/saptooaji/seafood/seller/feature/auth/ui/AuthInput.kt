package io.github.saptooaji.seafood.seller.feature.auth.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText

/**
 * Checks shared by sign-in and registration, run before sending so the error
 * shows at once. The server stays the final judge.
 */
object AuthInput {
    /** The longest name or email the API takes. */
    const val MAX_LENGTH = 255

    /** Used when a 429 has no `Retry-After`. */
    const val DEFAULT_RETRY_AFTER_SECONDS = 60L

    private val EMAIL_PATTERN = Regex("^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$")

    /** @param email already trimmed */
    fun emailError(email: String): UiText? =
        when {
            email.isEmpty() -> UiText.Resource(R.string.auth_error_email_required)
            email.length > MAX_LENGTH -> UiText.Resource(R.string.auth_error_email_too_long)
            !EMAIL_PATTERN.matches(email) -> UiText.Resource(R.string.auth_error_email_invalid)
            else -> null
        }
}
