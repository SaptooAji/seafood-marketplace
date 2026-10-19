package io.github.saptooaji.seafood.seller.feature.start

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus

enum class StartDestination { Checking, Login, OpenStore, StoreStatus, Main, Offline }

/**
 * Where a seller lands after the app opens or after signing in. The server
 * decides the store's status; the app only follows it (AC-7).
 */
object StartDestinationResolver {
    /** @param store the answer of `GET seller/store`; null when there is no token to ask with */
    fun resolve(
        hasToken: Boolean,
        store: ApiResult<CurrentStore>?,
    ): StartDestination =
        when {
            !hasToken || store == null -> StartDestination.Login
            // AuthInterceptor has already deleted a refused token.
            store is ApiResult.Unauthorized -> StartDestination.Login
            store is ApiResult.Success -> forStore(store.data)
            // No connection or a server error: the seller stays signed in and tries again.
            else -> StartDestination.Offline
        }

    private fun forStore(store: CurrentStore): StartDestination =
        when (store) {
            CurrentStore.NoStore -> StartDestination.OpenStore
            is CurrentStore.Exists ->
                if (store.store.status == StoreStatus.Active) StartDestination.Main else StartDestination.StoreStatus
        }
}
