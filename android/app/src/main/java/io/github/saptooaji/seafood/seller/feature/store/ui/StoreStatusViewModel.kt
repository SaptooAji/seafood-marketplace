package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/** What the status screen shows of the store; the bank account is left out on purpose. */
data class StoreStatusInfo(
    val name: String,
    val status: StoreStatus,
    /** The server's label, so the app says what the admin panel says. */
    val statusLabel: String,
    val rejectionReason: String?,
)

data class StoreStatusUiState(
    val isLoading: Boolean = true,
    val store: StoreStatusInfo? = null,
    /** Why another screen was refused, such as the suspension message of a 403. */
    val serverMessage: String? = null,
    /** The last check had no connection (or a server error); the seller is still signed in. */
    val offline: Boolean = false,
    val isChecking: Boolean = false,
    val isSigningOut: Boolean = false,
    /** Where to go next; set once, then the screen navigates. */
    val destination: StoreStatusDestination? = null,
)

enum class StoreStatusDestination { Main, OpenStore, Login }

sealed interface StoreStatusEvent {
    /** "Periksa lagi", or "Coba lagi" when offline. */
    data object CheckAgain : StoreStatusEvent

    data object SignOut : StoreStatusEvent
}

/**
 * The store waiting for, or refused by, the admin. The server decides the
 * status (AC-7); this screen reads it again on request and moves on once it
 * changes.
 */
@HiltViewModel
class StoreStatusViewModel
    @Inject
    constructor(
        private val stores: StoreRepository,
        private val auth: AuthRepository,
        savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(StoreStatusUiState(serverMessage = savedStateHandle[KEY_SERVER_MESSAGE]))
        val uiState: StateFlow<StoreStatusUiState> = _uiState.asStateFlow()

        init {
            load()
        }

        fun onEvent(event: StoreStatusEvent) {
            when (event) {
                StoreStatusEvent.CheckAgain -> load()
                StoreStatusEvent.SignOut -> signOut()
            }
        }

        private fun load() {
            val state = _uiState.value
            if (state.isChecking || state.isSigningOut) return

            _uiState.update { it.copy(isChecking = true) }
            viewModelScope.launch {
                val result = stores.current()
                _uiState.update { show(it.copy(isChecking = false, isLoading = false), result) }
            }
        }

        private fun show(
            state: StoreStatusUiState,
            result: ApiResult<CurrentStore>,
        ): StoreStatusUiState =
            when (result) {
                is ApiResult.Success ->
                    when (val current = result.data) {
                        CurrentStore.NoStore -> state.copy(offline = false, destination = StoreStatusDestination.OpenStore)
                        is CurrentStore.Exists -> shown(state, current.store)
                    }
                // AuthInterceptor has ended the session; the navigation is already on its way to sign-in.
                ApiResult.Unauthorized -> state
                else -> state.copy(offline = true)
            }

        private fun shown(
            state: StoreStatusUiState,
            store: Store,
        ): StoreStatusUiState {
            val info = StoreStatusInfo(store.name, store.status, store.statusLabel, store.rejectionReason)
            return if (store.status == StoreStatus.Active) {
                state.copy(store = info, offline = false, destination = StoreStatusDestination.Main)
            } else {
                state.copy(store = info, offline = false)
            }
        }

        private fun signOut() {
            if (_uiState.value.isSigningOut) return

            _uiState.update { it.copy(isSigningOut = true) }
            viewModelScope.launch {
                auth.logout()
                // Nothing of the store stays in memory after signing out (MOB-6).
                _uiState.value = StoreStatusUiState(isLoading = false, isSigningOut = true, destination = StoreStatusDestination.Login)
            }
        }

        private companion object {
            /** The name of [io.github.saptooaji.seafood.seller.navigation.StoreStatusRoute.serverMessage]. */
            const val KEY_SERVER_MESSAGE = "serverMessage"
        }
    }
