package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPhotoRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File

/** A part of the form with its own events, such as the variants or the photos. */
internal interface FormPart {
    /** Handles [event] when it belongs to this part; false for the form's other events. */
    fun handle(event: ProductFormEvent): Boolean
}

/** The most photos a product may have, as the server's `catalog.max_photos`. */
const val MAX_PHOTOS = 8

/** Where a new photo comes from; the screen opens the camera app or the Photo Picker. */
enum class PhotoSource { Camera, Gallery }

/** A photo in the form's photo row: saved on the server, or still on the phone. */
sealed interface FormPhoto {
    val key: String
}

data class SavedPhoto(
    val image: ProductImage,
) : FormPhoto {
    override val key: String get() = "saved-${image.id}"
}

/** A prepared photo in the cache at [path], not yet on the server. */
data class PendingPhoto(
    override val key: String,
    val path: String,
    val status: PendingStatus,
) : FormPhoto

sealed interface PendingStatus {
    data object Queued : PendingStatus

    data class Uploading(
        val progress: Float,
    ) : PendingStatus

    /** Refused or not sent; the seller tries again or takes it off. */
    data class Failed(
        val message: UiText,
    ) : PendingStatus

    /** Held back by a used-up upload limit until the seller goes on. */
    data object Paused : PendingStatus

    /** Queued or on its way: the server's photo order is about to change. */
    val isWaitingToSend: Boolean get() = this == Queued || this is Uploading
}

/**
 * The photos of a saved product (#241), part of [ProductFormViewModel].
 * Each upload, reorder, and delete goes to the server at once, apart from the
 * form's Save, as the variants do. Uploads go one at a time; a used-up limit
 * stops the rest without retrying on its own.
 */
internal class PhotoEditor(
    private val productId: Long?,
    private val photos: ProductPhotoRepository,
    private val state: MutableStateFlow<ProductFormUiState>,
    private val scope: CoroutineScope,
) : FormPart {
    private var uploads: Job? = null
    private var nextKey = 0

    /** Handles a photo event; false for the form's other events. */
    override fun handle(event: ProductFormEvent): Boolean {
        when (event) {
            is ProductFormEvent.PhotoPrepared -> add(event.path)
            is ProductFormEvent.RetryPhoto -> requeue { it.key == event.key && it.status is PendingStatus.Failed }
            is ProductFormEvent.RemovePendingPhoto -> remove(event.key)
            ProductFormEvent.ResumeUploads -> requeue { it.status == PendingStatus.Paused }
            is ProductFormEvent.OpenPhotoActions ->
                state.update { s -> s.copy(photoActions = event.key.takeIf { key -> s.photos.any { it is SavedPhoto && it.key == key } }) }
            ProductFormEvent.DismissPhotoActions -> state.update { it.copy(photoActions = null) }
            is ProductFormEvent.MakeMainPhoto -> {
                state.update { it.copy(photoActions = null) }
                move(from = state.value.photos.indexOfFirst { it.key == event.key }, to = 0)
            }
            is ProductFormEvent.MovePhoto -> move(event.from, event.to)
            is ProductFormEvent.DeletePhoto, ProductFormEvent.ConfirmDeletePhoto, ProductFormEvent.DismissDeletePhoto -> delete(event)
            else -> return false
        }
        return true
    }

    /** The cache files of photos never uploaded, when the form goes away. */
    fun discard() {
        state.value.photos
            .filterIsInstance<PendingPhoto>()
            .forEach { File(it.path).delete() }
    }

    private fun add(path: String) {
        if (productId == null || !state.value.canAddPhoto) {
            File(path).delete()
            return
        }
        nextKey++
        state.update { it.copy(photos = it.photos + PendingPhoto("pending-$nextKey", path, PendingStatus.Queued)) }
        runQueue()
    }

    private fun requeue(which: (PendingPhoto) -> Boolean) {
        state.update { s ->
            s.copy(
                photos = s.photos.map { if (it is PendingPhoto && which(it)) it.copy(status = PendingStatus.Queued) else it },
                photoNotice = null,
            )
        }
        runQueue()
    }

    private fun remove(key: String) {
        val photo = state.value.photos.firstOrNull { it.key == key } as? PendingPhoto ?: return
        if (photo.status is PendingStatus.Uploading) return
        File(photo.path).delete()
        state.update { s -> s.copy(photos = s.photos.filterNot { it.key == key }) }
    }

    private fun runQueue() {
        val id = productId ?: return
        if (uploads?.isActive == true) return
        uploads =
            scope.launch {
                var next = state.value.nextQueued()
                while (next != null) {
                    val photo = next
                    setStatus(photo.key, PendingStatus.Uploading(0f))
                    val result =
                        photos.upload(id, File(photo.path)) { progress ->
                            // From the network thread; only a photo still uploading takes it.
                            setStatus(photo.key, PendingStatus.Uploading(progress)) { it is PendingStatus.Uploading }
                        }
                    next = if (settle(photo, result)) state.value.nextQueued() else null
                }
            }
    }

    /** The upload's answer in the row; false when the rest of the queue must stop. */
    private fun settle(
        photo: PendingPhoto,
        result: ApiResult<ProductImage>,
    ): Boolean {
        when (result) {
            is ApiResult.Success -> {
                File(photo.path).delete()
                state.update { s -> s.copy(photos = s.photos.map { if (it.key == photo.key) SavedPhoto(result.data) else it }) }
            }
            is ApiResult.TooManyRequests -> {
                // This photo and the rest wait; nothing is retried on its own.
                state.update { s ->
                    val photos =
                        s.photos.map {
                            if (it is PendingPhoto &&
                                it.status.isWaitingToSend
                            ) {
                                it.copy(status = PendingStatus.Paused)
                            } else {
                                it
                            }
                        }
                    val held = photos.count { it is PendingPhoto && it.status == PendingStatus.Paused }
                    s.copy(photos = photos, photoNotice = pausedMessage(held, result))
                }
                return false
            }
            else -> setStatus(photo.key, PendingStatus.Failed(uploadFailure(result)))
        }
        return true
    }

    /** [status] for the pending photo [key], when its current status passes [only]. */
    private fun setStatus(
        key: String,
        status: PendingStatus,
        only: (PendingStatus) -> Boolean = { true },
    ) {
        state.update { s ->
            s.copy(photos = s.photos.map { if (it is PendingPhoto && it.key == key && only(it.status)) it.copy(status = status) else it })
        }
    }

    /** Moves a saved photo and sends the whole order at once; a refusal puts it back. */
    private fun move(
        from: Int,
        to: Int,
    ) {
        val id = productId ?: return
        val current = state.value
        val saved = current.photos.filterIsInstance<SavedPhoto>()
        val moves = from != to && from in saved.indices && to in saved.indices
        if (!current.canReorderPhotos || !moves) return
        val reordered = saved.toMutableList().apply { add(to, removeAt(from)) }
        state.update { s ->
            s.copy(photos = reordered + s.photos.filterIsInstance<PendingPhoto>(), isReorderingPhotos = true, photoNotice = null)
        }
        scope.launch {
            val result = photos.reorder(id, reordered.map { it.image.id })
            state.update { s ->
                val pending = s.photos.filterIsInstance<PendingPhoto>()
                when (result) {
                    is ApiResult.Success -> s.copy(photos = result.data.map(::SavedPhoto) + pending, isReorderingPhotos = false)
                    else ->
                        s.copy(
                            photos = saved + pending,
                            isReorderingPhotos = false,
                            photoNotice = UiText.Resource(R.string.product_photo_order_failed),
                        )
                }
            }
        }
    }

    /** Asks, then deletes a saved photo on the server; a refusal keeps it and says so. */
    private fun delete(event: ProductFormEvent) {
        when (event) {
            is ProductFormEvent.DeletePhoto -> state.update { it.copy(photoActions = null, photoToDelete = event.key) }
            ProductFormEvent.DismissDeletePhoto -> state.update { it.copy(photoToDelete = null) }
            else -> {
                val key = state.value.photoToDelete
                val photo = state.value.photos.firstOrNull { it.key == key } as? SavedPhoto
                state.update { it.copy(photoToDelete = null, photoNotice = null) }
                val id = productId
                if (id != null && photo != null) {
                    scope.launch {
                        when (photos.delete(id, photo.image.id)) {
                            // Already gone is what the seller asked for.
                            is ApiResult.Success, ApiResult.NotFound ->
                                state.update { s -> s.copy(photos = s.photos.filterNot { it.key == photo.key }) }
                            else -> state.update { it.copy(photoNotice = UiText.Resource(R.string.product_photo_delete_failed)) }
                        }
                    }
                }
            }
        }
    }
}

private fun ProductFormUiState.nextQueued(): PendingPhoto? =
    photos.firstOrNull { it is PendingPhoto && it.status == PendingStatus.Queued } as? PendingPhoto

private const val SECONDS_PER_MINUTE = 60L

/** A wait of an hour or more is the store's daily quota, not the per-minute limit. */
private const val QUOTA_WAIT_SECONDS = 3600L

/**
 * Why the queue stopped. A short wait is told in the app's words, since the
 * general API limit answers in English; the daily quota in the server's.
 */
private fun pausedMessage(
    held: Int,
    limit: ApiResult.TooManyRequests,
): UiText {
    val seconds = limit.retryAfterSeconds
    return when {
        seconds == null -> UiText.Resource(R.string.product_photo_paused_later, listOf(held))
        seconds >= QUOTA_WAIT_SECONDS -> limit.message?.let(UiText::Raw) ?: UiText.Resource(R.string.product_photo_quota_used)
        else -> {
            val minutes = ((seconds + SECONDS_PER_MINUTE - 1) / SECONDS_PER_MINUTE).coerceAtLeast(1)
            UiText.Resource(R.string.product_photo_paused, listOf(held, minutes))
        }
    }
}

/** The server's own word on a refused photo, or what went wrong on the way. */
private fun uploadFailure(result: ApiResult<*>): UiText =
    when (result) {
        is ApiResult.Invalid ->
            (result.fieldErrors["photo"]?.firstOrNull() ?: result.message)?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server)
        else -> result.message()
    }
