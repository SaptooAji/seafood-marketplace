package io.github.saptooaji.seafood.seller.feature.start

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Decides where the app opens. Without a token: sign-in, with no API call.
 * With one: `GET seller/store` both checks the token and tells the store's
 * status ([StartDestinationResolver]). Without a connection, or with our
 * server down, the seller stays signed in and sees a screen to try again.
 */
@HiltViewModel
class StartViewModel
    @Inject
    constructor(
        private val tokenStore: TokenStore,
        private val repository: StoreRepository,
    ) : ViewModel() {
        private val _destination = MutableStateFlow(StartDestination.Checking)
        val destination: StateFlow<StartDestination> = _destination.asStateFlow()

        init {
            check()
        }

        fun retry() {
            if (!_destination.value.isTrouble()) return
            _destination.value = StartDestination.Checking
            check()
        }

        private fun check() {
            viewModelScope.launch {
                val hasToken = tokenStore.token().first() != null
                val store = if (hasToken) repository.current() else null
                _destination.value = StartDestinationResolver.resolve(hasToken, store)
            }
        }
    }
