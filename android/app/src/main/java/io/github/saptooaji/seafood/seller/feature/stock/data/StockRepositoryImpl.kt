package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import kotlinx.serialization.json.Json

class StockRepositoryImpl(
    private val api: StockApi,
    private val json: Json,
) : StockRepository {
    override suspend fun today(): ApiResult<List<StockProduct>> =
        apiCall(json) { api.today() }.map { page ->
            page.data.map { it.toDomain() }
        }

    override suspend fun confirm(counts: List<StockCount>): ApiResult<List<StockProduct>> =
        apiCall(json) { api.confirm(ConfirmStockRequest.from(counts)) }.map { page -> page.data.map { it.toDomain() } }
}
