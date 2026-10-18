package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.core.util.launchCountdown
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.Registration
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Creating a seller account. The name, email, and phone are kept in the
 * [SavedStateHandle] (rotation, process death) and in the [RegistrationDraft]
 * (going back to sign-in and returning). The passwords live in memory only.
 */
@HiltViewModel
class RegisterViewModel
    @Inject
    constructor(
        private val repository: AuthRepository,
        private val draft: RegistrationDraft,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState =
            MutableStateFlow(
                RegisterUiState(
                    name = savedStateHandle[KEY_NAME] ?: draft.name,
                    email = savedStateHandle[KEY_EMAIL] ?: draft.email,
                    phone = savedStateHandle[KEY_PHONE] ?: draft.phone,
                ),
            )
        val uiState: StateFlow<RegisterUiState> = _uiState.asStateFlow()

        fun onEvent(event: RegisterEvent) {
            when (event) {
                is RegisterEvent.NameChanged -> {
                    remember(KEY_NAME, event.name) { draft.name = it }
                    edit { copy(name = event.name, nameError = null) }
                }
                is RegisterEvent.EmailChanged -> {
                    remember(KEY_EMAIL, event.email) { draft.email = it }
                    edit { copy(email = event.email, emailError = null) }
                }
                is RegisterEvent.PhoneChanged -> {
                    remember(KEY_PHONE, event.phone) { draft.phone = it }
                    edit { copy(phone = event.phone, phoneError = null) }
                }
                is RegisterEvent.PasswordChanged -> edit { copy(password = event.password, passwordError = null) }
                is RegisterEvent.PasswordConfirmationChanged ->
                    edit { copy(passwordConfirmation = event.passwordConfirmation, passwordConfirmationError = null) }
                RegisterEvent.Submit -> submit()
            }
        }

        private fun remember(
            key: String,
            value: String,
            intoDraft: (String) -> Unit,
        ) {
            savedStateHandle[key] = value
            intoDraft(value)
        }

        /** Applies a field change; a general error clears too, unless a 429 countdown is running. */
        private fun edit(change: RegisterUiState.() -> RegisterUiState) {
            _uiState.update { state ->
                val changed = state.change()
                if (changed.retryAfterSeconds > 0) changed else changed.copy(generalError = null)
            }
        }

        private fun submit() {
            val state = _uiState.value
            if (!state.canSubmit) return

            // Name and email are trimmed and the phone loses its spaces and
            // dashes; the passwords are sent exactly as typed.
            val registration =
                Registration(
                    name = state.name.trim(),
                    email = state.email.trim(),
                    phone = PhoneNumber.normalize(state.phone),
                    password = state.password,
                    passwordConfirmation = state.passwordConfirmation,
                )
            val checked = validate(state, registration)
            if (checked.hasFieldError()) {
                _uiState.value = checked
                return
            }

            _uiState.update { it.copy(isSubmitting = true, generalError = null) }
            viewModelScope.launch {
                val result = repository.register(registration)
                _uiState.update { it.copy(isSubmitting = false) }
                handle(result)
            }
        }

        private fun validate(
            state: RegisterUiState,
            registration: Registration,
        ): RegisterUiState =
            state.copy(
                nameError =
                    when {
                        registration.name.isEmpty() -> UiText.Resource(R.string.register_error_name_required)
                        registration.name.length > AuthInput.MAX_LENGTH -> UiText.Resource(R.string.register_error_name_too_long)
                        else -> null
                    },
                emailError = AuthInput.emailError(registration.email),
                phoneError = if (PhoneNumber.isValid(registration.phone)) null else UiText.Resource(R.string.register_error_phone_invalid),
                passwordError =
                    if (registration.password.codePointCount(0, registration.password.length) < MIN_PASSWORD_LENGTH) {
                        UiText.Resource(R.string.register_error_password_too_short)
                    } else {
                        null
                    },
                passwordConfirmationError =
                    if (registration.passwordConfirmation != registration.password) {
                        UiText.Resource(R.string.register_error_password_mismatch)
                    } else {
                        null
                    },
            )

        private fun RegisterUiState.hasFieldError() =
            listOf(nameError, emailError, phoneError, passwordError, passwordConfirmationError).any { it != null }

        private fun handle(result: ApiResult<User>) {
            when (result) {
                is ApiResult.Success -> {
                    draft.clear()
                    _uiState.update { it.copy(registered = true) }
                }
                is ApiResult.Invalid -> showServerErrors(result.fieldErrors)
                is ApiResult.TooManyRequests -> countDown(result.retryAfterSeconds ?: AuthInput.DEFAULT_RETRY_AFTER_SECONDS)
                ApiResult.NetworkError -> _uiState.update { it.copy(generalError = UiText.Resource(R.string.error_network)) }
                else -> _uiState.update { it.copy(generalError = UiText.Resource(R.string.error_server)) }
            }
        }

        /**
         * The server's messages follow its locale, so each field gets the app's
         * own text instead. The device has already checked format and length,
         * so an email the server refuses is one already in use.
         */
        private fun showServerErrors(fieldErrors: Map<String, List<String>>) {
            _uiState.update { state ->
                var next = state
                for (field in fieldErrors.keys) {
                    next =
                        when (field) {
                            "name" -> next.copy(nameError = UiText.Resource(R.string.register_error_name_required))
                            "email" -> next.copy(emailError = UiText.Resource(R.string.register_error_email_taken))
                            "phone" -> next.copy(phoneError = UiText.Resource(R.string.register_error_phone_invalid))
                            "password" -> next.copy(passwordError = UiText.Resource(R.string.register_error_password_too_short))
                            "password_confirmation" ->
                                next.copy(passwordConfirmationError = UiText.Resource(R.string.register_error_password_mismatch))
                            else -> next.copy(generalError = UiText.Resource(R.string.error_server))
                        }
                }
                if (next == state) next.copy(generalError = UiText.Resource(R.string.error_server)) else next
            }
        }

        private fun countDown(seconds: Long) {
            viewModelScope.launchCountdown(
                seconds = seconds,
                onTick = { left ->
                    _uiState.update {
                        it.copy(retryAfterSeconds = left, generalError = UiText.Resource(R.string.auth_error_too_many, listOf(left)))
                    }
                },
                onDone = { _uiState.update { it.copy(retryAfterSeconds = 0, generalError = null) } },
            )
        }

        private companion object {
            const val KEY_NAME = "name"
            const val KEY_EMAIL = "email"
            const val KEY_PHONE = "phone"

            /** `Password::min(8)` on the server, which counts characters, not bytes. */
            const val MIN_PASSWORD_LENGTH = 8
        }
    }

/** An Indonesian mobile number, the same rule as `IndonesianPhone` on the server. */
object PhoneNumber {
    private val PATTERN = Regex("^(?:08|\\+628)\\d{8,13}$")
    private val SEPARATORS = Regex("[\\s-]")

    /** "0812 3456 7890" becomes "081234567890". */
    fun normalize(phone: String): String = phone.replace(SEPARATORS, "")

    /** @param phone already normalised */
    fun isValid(phone: String): Boolean = PATTERN.matches(phone)
}
