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

    /** One product with its attribute values, for the edit form. Another store's product is [ApiResult.NotFound]. */
    suspend fun get(id: Long): ApiResult<ProductDetail>

    /** Creates a product with its first variant (`POST seller/products`). */
    suspend fun create(
        draft: ProductDraft,
        variant: NewVariant,
    ): ApiResult<ProductDetail>

    /** Saves the product's own fields (`PUT seller/products/{id}`); variants are left alone. */
    suspend fun update(
        id: Long,
        draft: ProductDraft,
    ): ApiResult<ProductDetail>

    /** Soft deletes the product; its stock and order history stay. */
    suspend fun delete(id: Long): ApiResult<Unit>
}
