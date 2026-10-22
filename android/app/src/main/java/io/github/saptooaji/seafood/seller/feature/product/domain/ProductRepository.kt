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

    /**
     * An AI description and SEO summary for [draft], which may be half
     * written (`POST seller/products/description-suggestion`). Nothing is
     * saved. A used-up limit is [ApiResult.TooManyRequests], an AI that is off
     * or failing is [ApiResult.ServiceUnavailable].
     */
    suspend fun suggestDescription(draft: ProductDraft): ApiResult<DescriptionSuggestion>

    /** Soft deletes the product; its stock and order history stay. */
    suspend fun delete(id: Long): ApiResult<Unit>

    /** Adds a variant with its opening stock (`POST seller/products/{id}/variants`). */
    suspend fun addVariant(
        productId: Long,
        variant: NewVariant,
    ): ApiResult<ProductVariant>

    /**
     * Saves a variant's details (`PUT seller/variants/{id}`); its stock stays.
     * A variant deleted meanwhile is [ApiResult.NotFound].
     */
    suspend fun updateVariant(
        id: Long,
        changes: VariantChanges,
    ): ApiResult<ProductVariant>

    /**
     * Soft deletes a variant; its stock history stays. The product's last
     * variant is refused as [ApiResult.Invalid] on `variant`.
     */
    suspend fun deleteVariant(id: Long): ApiResult<Unit>
}
