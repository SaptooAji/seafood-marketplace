package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import kotlinx.serialization.json.Json

class ProductRepositoryImpl(
    private val api: ProductApi,
    private val json: Json,
) : ProductRepository {
    override suspend fun list(
        status: ProductStatusFilter,
        search: String?,
        page: Int,
    ): ApiResult<ProductPage> = apiCall(json) { api.list(status.query, search, page) }.map { it.toDomain() }
}
