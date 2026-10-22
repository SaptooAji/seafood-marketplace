package io.github.saptooaji.seafood.seller.feature.product.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import java.io.File

/**
 * The photos of a saved product (#241). Each call changes the product on the
 * server at once, apart from the form's Save. Another store's product is
 * [ApiResult.NotFound].
 */
interface ProductPhotoRepository {
    /**
     * Uploads a prepared JPEG after the product's other photos
     * (`POST seller/products/{id}/images`). [onProgress] gets the share of
     * the file sent, from 0 to 1. A used-up limit is
     * [ApiResult.TooManyRequests]; a refused photo is [ApiResult.Invalid] on `photo`.
     */
    suspend fun upload(
        productId: Long,
        photo: File,
        onProgress: (Float) -> Unit,
    ): ApiResult<ProductImage>

    /** Puts every photo of the product in [imageIds]' order, first is main (`PUT .../images/order`). */
    suspend fun reorder(
        productId: Long,
        imageIds: List<Long>,
    ): ApiResult<List<ProductImage>>

    /** Deletes one photo and its files (`DELETE .../images/{imageId}`). */
    suspend fun delete(
        productId: Long,
        imageId: Long,
    ): ApiResult<Unit>
}
