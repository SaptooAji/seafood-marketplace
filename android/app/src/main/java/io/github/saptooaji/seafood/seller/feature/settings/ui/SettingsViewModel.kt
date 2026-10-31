package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.LocalTime
import javax.inject.Inject

/**
 * The Pengaturan tab (#245): the account, the store, the morning reminder,
 * and signing out. The server decides who may change the store (AC-2); the
 * tab only hides what staff cannot change.
 */
@HiltViewModel
class SettingsViewModel
    @Inject
    constructor(
        private val auth: AuthRepository,
        private val stores: StoreRepository,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(SettingsUiState())
        val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

        private var loading: Job? = null

        init {
            load()
        }

        fun onEvent(event: SettingsEvent) {
            when (event) {
                SettingsEvent.Retry -> load()
                is SettingsEvent.StoreEdited -> {
                    if (event.result == EditStoreResult.NotOwner) _uiState.update { it.copy(notice = SettingsNotice.OwnerOnly) }
                    load()
                }
                is SettingsEvent.SaveReminder -> saveReminder(event.time)
                SettingsEvent.NoticeShown -> _uiState.update { it.copy(notice = null) }
                SettingsEvent.AskSignOut -> _uiState.update { it.copy(confirmingSignOut = true) }
                SettingsEvent.CancelSignOut -> _uiState.update { it.copy(confirmingSignOut = false) }
                SettingsEvent.SignOut -> signOut()
                else -> onProfileEvent(event)
            }
        }

        private fun onProfileEvent(event: SettingsEvent) {
            when (event) {
                SettingsEvent.EditProfile ->
                    _uiState.update { state ->
                        state.user?.let { state.copy(profile = ProfileFormState(name = it.name, phone = it.phone.orEmpty())) } ?: state
                    }
                is SettingsEvent.ProfileChanged -> _uiState.update { it.copy(profile = it.profile?.with(event.field, event.value)) }
                SettingsEvent.CloseProfile -> _uiState.update { it.copy(profile = null) }
                SettingsEvent.SaveProfile -> saveProfile()
                else -> Unit
            }
        }

        private fun load() {
            if (_uiState.value.isSigningOut) return

            loading?.cancel()
            loading =
                viewModelScope.launch {
                    val user = async { auth.me() }
                    val store = async { stores.current() }
                    val (userResult, storeResult) = user.await() to store.await()
                    _uiState.update { shown(it, userResult, storeResult) }
                }
        }

        private fun shown(
            state: SettingsUiState,
            user: ApiResult<User>,
            store: ApiResult<CurrentStore>,
        ): SettingsUiState {
            val current = (store as? ApiResult.Success)?.data as? CurrentStore.Exists
            val failure = listOf(user, store).firstOrNull { it !is ApiResult.Success }
            return when {
                // AuthInterceptor has ended the session; the navigation is on its way to sign-in.
                failure == ApiResult.Unauthorized -> state
                user is ApiResult.Success && current != null ->
                    state.copy(isLoading = false, trouble = null, user = user.data, store = current.store)
                // Loaded before: keep it on screen and say why it did not refresh.
                state.store != null -> state.copy(notice = noticeFor(failure ?: ApiResult.ServerError))
                else -> state.copy(isLoading = false, trouble = ConnectionTrouble.of(failure ?: ApiResult.ServerError))
            }
        }

        private fun saveProfile() {
            val profile = _uiState.value.profile?.takeIf { !it.isSaving } ?: return
            val (checked, valid) = profile.validated()
            _uiState.update { it.copy(profile = checked.copy(isSaving = valid != null, generalError = null)) }
            if (valid == null) return

            viewModelScope.launch {
                val result = auth.updateProfile(valid.first, valid.second)
                _uiState.update { state ->
                    val sheet = state.profile?.copy(isSaving = false)
                    when (result) {
                        is ApiResult.Success -> state.copy(user = result.data, profile = null)
                        is ApiResult.Invalid ->
                            state.copy(profile = sheet?.withServerErrors(result.fieldErrors) ?: sheet?.copy(generalError = SERVER))
                        ApiResult.NetworkError -> state.copy(profile = sheet?.copy(generalError = NETWORK))
                        ApiResult.Unauthorized -> state.copy(profile = sheet)
                        else -> state.copy(profile = sheet?.copy(generalError = SERVER))
                    }
                }
            }
        }

        private fun saveReminder(time: LocalTime?) {
            val state = _uiState.value
            val details = state.store?.takeIf { state.isOwner && !state.isSavingReminder }?.toDetails(time) ?: return

            _uiState.update { it.copy(isSavingReminder = true) }
            viewModelScope.launch {
                val result = stores.update(details)
                _uiState.update { it.copy(isSavingReminder = false) }
                when (result) {
                    is ApiResult.Success -> _uiState.update { it.copy(store = result.data) }
                    is ApiResult.Forbidden -> {
                        _uiState.update { it.copy(notice = SettingsNotice.OwnerOnly) }
                        load()
                    }
                    ApiResult.Unauthorized -> Unit
                    else -> _uiState.update { it.copy(notice = noticeFor(result)) }
                }
            }
        }

        private fun signOut() {
            if (_uiState.value.isSigningOut) return

            loading?.cancel()
            _uiState.update { it.copy(isSigningOut = true) }
            viewModelScope.launch {
                auth.logout()
                // Nothing of the account or the store stays in memory after signing out (MOB-6).
                _uiState.value = SettingsUiState(isLoading = false, isSigningOut = true, signedOut = true)
            }
        }

        private fun noticeFor(result: ApiResult<*>): SettingsNotice =
            if (result == ApiResult.NetworkError) SettingsNotice.NoInternet else SettingsNotice.Server

        private companion object {
            val NETWORK = UiText.Resource(R.string.error_network)
            val SERVER = UiText.Resource(R.string.error_server)
        }
    }
