package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import kotlinx.serialization.json.Json

class StoreRepositoryImpl(
    private val api: StoreApi,
    private val json: Json,
) : StoreRepository {
    override suspend fun open(details: StoreDetails): ApiResult<Store> =
        apiCall(json) { api.open(StoreRequest.from(details)) }.map { it.data.toDomain() }
}
