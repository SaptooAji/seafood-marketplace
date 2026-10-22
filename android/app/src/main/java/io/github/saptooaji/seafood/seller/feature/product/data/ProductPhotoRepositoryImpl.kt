package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPhotoRepository
import kotlinx.serialization.json.Json
import okhttp3.MediaType
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okio.BufferedSink
import okio.source
import java.io.File

class ProductPhotoRepositoryImpl(
    private val api: ProductPhotoApi,
    private val json: Json,
) : ProductPhotoRepository {
    override suspend fun upload(
        productId: Long,
        photo: File,
        onProgress: (Float) -> Unit,
    ): ApiResult<ProductImage> {
        // Always `photo.jpg`: the name of the picked file never leaves the phone (#241).
        val part = MultipartBody.Part.createFormData(PART_NAME, FILE_NAME, ProgressBody(photo, JPEG, onProgress))
        return apiCall(json) { api.uploadImage(productId, part) }.map { it.data.toDomain() }
    }

    override suspend fun reorder(
        productId: Long,
        imageIds: List<Long>,
    ): ApiResult<List<ProductImage>> =
        apiCall(json) { api.reorderImages(productId, ImageOrderRequest(imageIds)) }.map { images -> images.data.map { it.toDomain() } }

    override suspend fun delete(
        productId: Long,
        imageId: Long,
    ): ApiResult<Unit> = apiCall(json) { api.deleteImage(productId, imageId) }

    private companion object {
        const val PART_NAME = "photo"
        const val FILE_NAME = "photo.jpg"
        val JPEG = "image/jpeg".toMediaType()
    }
}

/**
 * A file body that reports the share of it written. A retried request
 * writes the body again, so the share starts over.
 */
private class ProgressBody(
    private val file: File,
    private val type: MediaType,
    private val onProgress: (Float) -> Unit,
) : RequestBody() {
    override fun contentType() = type

    override fun contentLength() = file.length()

    // Written once: not again by a silent retry, and not into the debug log.
    override fun isOneShot() = true

    override fun writeTo(sink: BufferedSink) {
        val total = contentLength().coerceAtLeast(1)
        var sent = 0L
        file.source().use { source ->
            while (true) {
                val read = source.read(sink.buffer, CHUNK_BYTES)
                if (read == -1L) break
                sink.flush()
                sent += read
                onProgress(sent.toFloat() / total)
            }
        }
        if (sent == 0L) onProgress(1f)
    }

    private companion object {
        const val CHUNK_BYTES = 16L * 1024
    }
}
