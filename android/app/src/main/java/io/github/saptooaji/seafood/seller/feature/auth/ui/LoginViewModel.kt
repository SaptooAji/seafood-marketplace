package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Sign-in with email and password. Only the email is kept in the
 * [SavedStateHandle]; the password lives in memory only, so it never outlives
 * the process.
 */
@HiltViewModel
class LoginViewModel
    @Inject
    constructor(
        private val repository: AuthRepository,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState =
            MutableStateFlow(
                LoginUiState(
                    email = savedStateHandle[KEY_EMAIL] ?: "",
                    sessionExpired = savedStateHandle[KEY_SESSION_EXPIRED] ?: false,
                ),
            )
        val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

        fun onEvent(event: LoginEvent) {
            when (event) {
                is LoginEvent.EmailChanged -> {
                    savedStateHandle[KEY_EMAIL] = event.email
                    _uiState.update { it.copy(email = event.email, emailError = null).clearGeneralError() }
                }
                is LoginEvent.PasswordChanged ->
                    _uiState.update { it.copy(password = event.password, passwordError = null).clearGeneralError() }
                LoginEvent.Submit -> submit()
            }
        }

        private fun submit() {
            val state = _uiState.value
            if (!state.canSubmit) return

            // The email is trimmed; the password is sent exactly as typed.
            val email = state.email.trim()
            val emailError = validateEmail(email)
            val passwordError = if (state.password.isEmpty()) UiText.Resource(R.string.login_error_password_required) else null
            if (emailError != null || passwordError != null) {
                _uiState.update { it.copy(emailError = emailError, passwordError = passwordError) }
                return
            }

            _uiState.update { it.copy(isSubmitting = true, generalError = null, sessionExpired = false) }
            viewModelScope.launch {
                val result = repository.login(email, state.password)
                _uiState.update { it.copy(isSubmitting = false) }
                handle(result)
            }
        }

        private fun handle(result: ApiResult<User>) {
            when (result) {
                is ApiResult.Success -> _uiState.update { it.copy(loggedIn = true) }
                // AUTH-3: one message whatever the reason, in the app's own words.
                is ApiResult.Invalid, ApiResult.Unauthorized ->
                    _uiState.update { it.copy(emailError = UiText.Resource(R.string.login_error_credentials)) }
                is ApiResult.TooManyRequests -> countDown(result.retryAfterSeconds ?: DEFAULT_RETRY_AFTER_SECONDS)
                ApiResult.NetworkError -> showGeneralError(UiText.Resource(R.string.error_network))
                else -> showGeneralError(UiText.Resource(R.string.error_server))
            }
        }

        private fun countDown(seconds: Long) {
            viewModelScope.launch {
                for (left in seconds downTo 1) {
                    _uiState.update {
                        it.copy(retryAfterSeconds = left, generalError = UiText.Resource(R.string.login_error_too_many, listOf(left)))
                    }
                    delay(ONE_SECOND_MILLIS)
                }
                _uiState.update { it.copy(retryAfterSeconds = 0, generalError = null) }
            }
        }

        private fun showGeneralError(text: UiText) {
            _uiState.update { it.copy(generalError = text) }
        }

        /** A 429 message stays until its countdown ends; other general errors clear on typing. */
        private fun LoginUiState.clearGeneralError() = if (retryAfterSeconds > 0) this else copy(generalError = null)

        private fun validateEmail(email: String): UiText? =
            when {
                email.isEmpty() -> UiText.Resource(R.string.login_error_email_required)
                email.length > MAX_EMAIL_LENGTH -> UiText.Resource(R.string.login_error_email_too_long)
                !EMAIL_PATTERN.matches(email) -> UiText.Resource(R.string.login_error_email_invalid)
                else -> null
            }

        private companion object {
            const val KEY_EMAIL = "email"

            /** The argument of `LoginRoute`, filled in by Navigation. */
            const val KEY_SESSION_EXPIRED = "sessionExpired"
            const val MAX_EMAIL_LENGTH = 255
            const val DEFAULT_RETRY_AFTER_SECONDS = 60L
            const val ONE_SECOND_MILLIS = 1_000L

            // A light check before sending; the server has the final say.
            val EMAIL_PATTERN = Regex("^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$")
        }
    }
