package io.github.saptooaji.seafood.seller.feature.start

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.launch
import javax.inject.Inject

enum class StartDestination { Checking, SignIn, Main }

/**
 * Decides where the app opens. Without a token: sign-in, with no API call.
 * With one: `GET me` checks it, and only a 401 sends the seller to sign in, so
 * a seller without a connection stays signed in.
 */
@HiltViewModel
class StartViewModel
    @Inject
    constructor(
        private val tokenStore: TokenStore,
        private val repository: AuthRepository,
    ) : ViewModel() {
        private val _destination = MutableStateFlow(StartDestination.Checking)
        val destination: StateFlow<StartDestination> = _destination.asStateFlow()

        init {
            viewModelScope.launch {
                _destination.value =
                    when {
                        tokenStore.token().first() == null -> StartDestination.SignIn
                        // AuthInterceptor has already deleted the refused token.
                        repository.me() is ApiResult.Unauthorized -> StartDestination.SignIn
                        else -> StartDestination.Main
                    }
            }
        }
    }
