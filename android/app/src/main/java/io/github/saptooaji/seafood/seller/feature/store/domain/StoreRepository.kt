package io.github.saptooaji.seafood.seller.feature.store.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/** The signed-in seller's own store. */
interface StoreRepository {
    /**
     * The store the seller belongs to (`GET seller/store`). A seller without
     * one gets [CurrentStore.NoStore] rather than an error.
     */
    suspend fun current(): ApiResult<CurrentStore>

    /**
     * Opens a store for the seller (`POST seller/store`); it waits for an
     * admin as pending. A seller who already owns one gets [ApiResult.Conflict].
     */
    suspend fun open(details: StoreDetails): ApiResult<Store>
}

/** Whether the signed-in seller has a store yet. */
sealed interface CurrentStore {
    data object NoStore : CurrentStore

    data class Exists(
        val store: Store,
    ) : CurrentStore
}
