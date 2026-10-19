package io.github.saptooaji.seafood.seller.feature.product.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/** The products of the seller's store; the server picks the store from the user. */
interface ProductRepository {
    /**
     * One page of 20 products, newest first (`GET seller/products`).
     *
     * @param search part of the name, or null for every product
     */
    suspend fun list(
        status: ProductStatusFilter,
        search: String?,
        page: Int,
    ): ApiResult<ProductPage>
}
