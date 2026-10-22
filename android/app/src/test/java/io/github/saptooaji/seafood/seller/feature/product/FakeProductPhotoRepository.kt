package io.github.saptooaji.seafood.seller.feature.product

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPhotoRepository
import kotlinx.coroutines.CompletableDeferred
import java.io.File

/** A [ProductPhotoRepository] whose answers each test sets, recording every call. */
class FakeProductPhotoRepository : ProductPhotoRepository {
    data class Upload(
        val productId: Long,
        val path: String,
    )

    val uploads = mutableListOf<Upload>()

    /** Answers by upload number, from 1; an upload without one gets a new image with id 100 + its number. */
    val uploadAnswers = mutableMapOf<Int, ApiResult<ProductImage>>()

    /** By upload number: the upload reports [PROGRESS] and waits until the gate completes. */
    val uploadGates = mutableMapOf<Int, CompletableDeferred<Unit>>()

    /** How many uploads run at once at most, to check they go one by one. */
    var mostAtOnce = 0
    private var running = 0

    override suspend fun upload(
        productId: Long,
        photo: File,
        onProgress: (Float) -> Unit,
    ): ApiResult<ProductImage> {
        val number = uploads.size + 1
        uploads += Upload(productId, photo.path)
        running++
        mostAtOnce = maxOf(mostAtOnce, running)
        try {
            onProgress(PROGRESS)
            uploadGates[number]?.await()
            onProgress(1f)
        } finally {
            running--
        }
        return uploadAnswers[number] ?: ApiResult.Success(image(100L + number))
    }

    val reorders = mutableListOf<Pair<Long, List<Long>>>()

    /** Null answers with the photos in the order sent. */
    var reorderAnswer: ApiResult<List<ProductImage>>? = null

    override suspend fun reorder(
        productId: Long,
        imageIds: List<Long>,
    ): ApiResult<List<ProductImage>> {
        reorders += productId to imageIds
        return reorderAnswer ?: ApiResult.Success(imageIds.map { image(it) })
    }

    val deletes = mutableListOf<Pair<Long, Long>>()
    var deleteAnswer: ApiResult<Unit> = ApiResult.Success(Unit)

    override suspend fun delete(
        productId: Long,
        imageId: Long,
    ): ApiResult<Unit> {
        deletes += productId to imageId
        return deleteAnswer
    }

    companion object {
        /** What a gated upload reports while it waits. */
        const val PROGRESS = 0.4f

        fun image(id: Long) =
            ProductImage(id, "http://localhost:8000/storage/p/$id.webp", "http://localhost:8000/storage/p/${id}_thumb.webp")
    }
}
