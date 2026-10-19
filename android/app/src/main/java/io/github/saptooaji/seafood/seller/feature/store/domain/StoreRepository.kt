package io.github.saptooaji.seafood.seller.feature.store.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/** The signed-in seller's own store. */
interface StoreRepository {
    /**
     * Opens a store for the seller (`POST seller/store`); it waits for an
     * admin as pending. A seller who already owns one gets [ApiResult.Conflict].
     */
    suspend fun open(details: StoreDetails): ApiResult<Store>
}
