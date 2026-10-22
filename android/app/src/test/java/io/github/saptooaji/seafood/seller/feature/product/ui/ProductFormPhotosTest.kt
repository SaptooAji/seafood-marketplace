package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductPhotoRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductPhotoRepository.Companion.PROGRESS
import io.github.saptooaji.seafood.seller.feature.product.FakeProductPhotoRepository.Companion.image
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.DETAIL
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension
import org.junit.jupiter.api.io.TempDir
import java.io.File

/**
 * The photos of a saved product (#241, boards VD-Produk-Form--foto-*): each
 * upload, reorder, and delete goes to the server at once, apart from the
 * form's own Save, as the variants do.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ProductFormPhotosTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    @TempDir
    lateinit var cache: File

    private val products = FakeProductRepository()
    private val categories = FakeCategoryRepository()
    private val photos = FakeProductPhotoRepository()

    private val ProductFormViewModel.state get() = uiState.value

    /** The edit form of [DETAIL] with photos a (1), b (2), and c (3), loaded. */
    private fun TestScope.editForm(images: List<Long> = listOf(1, 2, 3)): ProductFormViewModel {
        products.getResult = ApiResult.Success(DETAIL.copy(images = images.map { image(it) }))
        val vm = ProductFormViewModel(products, categories, photos, SavedStateHandle(mapOf("productId" to DETAIL.id)))
        advanceUntilIdle()
        return vm
    }

    /** A prepared photo in the cache, as the crop screen hands it over. */
    private fun prepared(name: String): String = File(cache, "$name.jpg").apply { writeBytes(byteArrayOf(1, 2, 3)) }.path

    private fun ProductFormViewModel.add(path: String) = onEvent(ProductFormEvent.PhotoPrepared(path))

    private fun ProductFormViewModel.savedIds() = state.photos.filterIsInstance<SavedPhoto>().map { it.image.id }

    private fun ProductFormViewModel.pending(path: String) = state.photos.filterIsInstance<PendingPhoto>().single { it.path == path }

    // --- Showing ---------------------------------------------------------

    @Test
    fun `the edit form shows the product's photos in their order`() =
        runTest(main.dispatcher) {
            val vm = editForm(listOf(3, 1, 2))

            assertEquals(listOf(3L, 1L, 2L), vm.savedIds())
            assertTrue(vm.state.canAddPhoto)
        }

    @Test
    fun `adding a product takes no photos yet`() =
        runTest(main.dispatcher) {
            val vm = ProductFormViewModel(products, categories, photos, SavedStateHandle())
            advanceUntilIdle()

            assertFalse(vm.state.canAddPhoto)
            vm.add(prepared("baru"))
            advanceUntilIdle()
            assertTrue(vm.state.photos.isEmpty())
            assertTrue(photos.uploads.isEmpty())
        }

    // --- Uploading -------------------------------------------------------

    @Test
    fun `prepared photos upload one at a time, in order, and become saved photos`() =
        runTest(main.dispatcher) {
            val vm = editForm(images = emptyList())
            val first = prepared("satu")
            val second = prepared("dua")
            val third = prepared("tiga")

            vm.add(first)
            vm.add(second)
            vm.add(third)
            advanceUntilIdle()

            assertEquals(listOf(first, second, third), photos.uploads.map { it.path })
            assertEquals(setOf(DETAIL.id), photos.uploads.map { it.productId }.toSet())
            assertEquals(1, photos.mostAtOnce)
            assertEquals(listOf(101L, 102L, 103L), vm.savedIds())
            // The cache copies are gone once uploaded.
            assertFalse(File(first).exists() || File(second).exists() || File(third).exists())
        }

    @Test
    fun `the photo being uploaded shows its progress and the next one waits`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            photos.uploadGates[1] = gate
            val vm = editForm()
            val first = prepared("satu")
            val second = prepared("dua")

            vm.add(first)
            vm.add(second)
            runCurrent()

            assertEquals(PendingStatus.Uploading(PROGRESS), vm.pending(first).status)
            assertEquals(PendingStatus.Queued, vm.pending(second).status)
            assertEquals(5, vm.state.photos.size)
            // The server order would change under a reorder, so it waits for the uploads.
            assertFalse(vm.state.canReorderPhotos)

            gate.complete(Unit)
            advanceUntilIdle()
            assertTrue(vm.state.canReorderPhotos)
        }

    @Test
    fun `a refused photo is marked with the server's message, and the rest still upload`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[2] =
                ApiResult.Invalid(mapOf("photo" to listOf("Produk paling banyak memiliki 8 foto.")), "Produk paling banyak memiliki 8 foto.")
            val vm = editForm(images = emptyList())
            val first = prepared("satu")
            val second = prepared("dua")
            val third = prepared("tiga")

            listOf(first, second, third).forEach { vm.add(it) }
            advanceUntilIdle()

            assertEquals(3, photos.uploads.size)
            assertEquals(PendingStatus.Failed(UiText.Raw("Produk paling banyak memiliki 8 foto.")), vm.pending(second).status)
            assertEquals(listOf(101L, 103L), vm.savedIds())
            // The refused photo keeps its file, to try again.
            assertTrue(File(second).exists())
        }

    @Test
    fun `no connection marks the photo, which can be tried again`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.NetworkError
            val vm = editForm(images = emptyList())
            val first = prepared("satu")
            vm.add(first)
            advanceUntilIdle()
            assertEquals(PendingStatus.Failed(UiText.Resource(R.string.error_network)), vm.pending(first).status)

            vm.onEvent(ProductFormEvent.RetryPhoto(vm.pending(first).key))
            advanceUntilIdle()

            assertEquals(2, photos.uploads.size)
            assertEquals(listOf(102L), vm.savedIds())
            assertFalse(File(first).exists())
        }

    @Test
    fun `a failed photo can be taken off without a request, and its file goes`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.ServerError
            val vm = editForm()
            val first = prepared("satu")
            vm.add(first)
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.RemovePendingPhoto(vm.pending(first).key))

            assertEquals(listOf(1L, 2L, 3L), vm.savedIds())
            assertTrue(vm.state.photos.none { it is PendingPhoto })
            assertFalse(File(first).exists())
            assertEquals(1, photos.uploads.size)
            assertTrue(photos.deletes.isEmpty())
        }

    @Test
    fun `too many uploads stop the rest of the queue until the seller goes on`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[2] = ApiResult.TooManyRequests(60, "Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi.")
            val vm = editForm(images = emptyList())
            val paths = listOf("satu", "dua", "tiga").map { prepared(it) }

            paths.forEach { vm.add(it) }
            advanceUntilIdle()

            // The third photo is not sent: no automatic retry.
            assertEquals(2, photos.uploads.size)
            assertEquals(PendingStatus.Paused, vm.pending(paths[1]).status)
            assertEquals(PendingStatus.Paused, vm.pending(paths[2]).status)
            assertEquals(UiText.Resource(R.string.product_photo_paused, listOf(2, 1L)), vm.state.photoNotice)

            vm.onEvent(ProductFormEvent.ResumeUploads)
            advanceUntilIdle()

            assertEquals(4, photos.uploads.size)
            assertEquals(listOf(101L, 103L, 104L), vm.savedIds())
            assertNull(vm.state.photoNotice)
        }

    @Test
    fun `the store's daily quota shows the server's message`() =
        runTest(main.dispatcher) {
            val quota = "Kuota unggah foto toko hari ini sudah habis. Coba lagi besok."
            photos.uploadAnswers[1] = ApiResult.TooManyRequests(36_000, quota)
            val vm = editForm()
            vm.add(prepared("satu"))
            advanceUntilIdle()

            assertEquals(UiText.Raw(quota), vm.state.photoNotice)
        }

    @Test
    fun `eight photos, uploaded or waiting, turn adding off`() =
        runTest(main.dispatcher) {
            photos.uploadGates[1] = CompletableDeferred()
            val vm = editForm(images = listOf(1, 2, 3, 4, 5))
            listOf("a", "b", "c").forEach { vm.add(prepared(it)) }
            runCurrent()

            assertEquals(8, vm.state.photos.size)
            assertFalse(vm.state.canAddPhoto)

            // A ninth that slips through is dropped, file and all.
            val ninth = prepared("sembilan")
            vm.add(ninth)
            runCurrent()
            assertEquals(8, vm.state.photos.size)
            assertFalse(File(ninth).exists())
        }

    // --- Ordering --------------------------------------------------------

    @Test
    fun `moving the third photo first sends the new order at once`() =
        runTest(main.dispatcher) {
            val vm = editForm()

            vm.onEvent(ProductFormEvent.MovePhoto(from = 2, to = 0))
            assertEquals(listOf(3L, 1L, 2L), vm.savedIds())
            advanceUntilIdle()

            assertEquals(listOf(DETAIL.id to listOf(3L, 1L, 2L)), photos.reorders)
            assertEquals(listOf(3L, 1L, 2L), vm.savedIds())
        }

    @Test
    fun `a failed reorder puts the photos back and says so`() =
        runTest(main.dispatcher) {
            photos.reorderAnswer = ApiResult.NetworkError
            val vm = editForm()

            vm.onEvent(ProductFormEvent.MovePhoto(from = 2, to = 0))
            advanceUntilIdle()

            assertEquals(listOf(1L, 2L, 3L), vm.savedIds())
            assertEquals(UiText.Resource(R.string.product_photo_order_failed), vm.state.photoNotice)
        }

    @Test
    fun `making a photo the main one moves it first`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.OpenPhotoActions("saved-2"))
            assertEquals("saved-2", vm.state.photoActions)

            vm.onEvent(ProductFormEvent.MakeMainPhoto("saved-2"))
            advanceUntilIdle()

            assertNull(vm.state.photoActions)
            assertEquals(listOf(DETAIL.id to listOf(2L, 1L, 3L)), photos.reorders)
            assertEquals(listOf(2L, 1L, 3L), vm.savedIds())
        }

    @Test
    fun `the main photo is already first, so nothing is sent`() =
        runTest(main.dispatcher) {
            val vm = editForm()

            vm.onEvent(ProductFormEvent.MakeMainPhoto("saved-1"))
            vm.onEvent(ProductFormEvent.MovePhoto(from = 1, to = 1))
            advanceUntilIdle()

            assertTrue(photos.reorders.isEmpty())
        }

    @Test
    fun `photos are not reordered while an upload runs`() =
        runTest(main.dispatcher) {
            photos.uploadGates[1] = CompletableDeferred()
            val vm = editForm()
            vm.add(prepared("satu"))
            runCurrent()

            vm.onEvent(ProductFormEvent.MovePhoto(from = 2, to = 0))
            vm.onEvent(ProductFormEvent.MakeMainPhoto("saved-3"))
            runCurrent()

            assertTrue(photos.reorders.isEmpty())
            assertEquals(listOf(1L, 2L, 3L), vm.savedIds())
        }

    // --- Deleting --------------------------------------------------------

    @Test
    fun `deleting asks first, then removes the photo on the server`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.OpenPhotoActions("saved-2"))

            vm.onEvent(ProductFormEvent.DeletePhoto("saved-2"))
            assertNull(vm.state.photoActions)
            assertEquals("saved-2", vm.state.photoToDelete)
            assertTrue(photos.deletes.isEmpty())

            vm.onEvent(ProductFormEvent.ConfirmDeletePhoto)
            advanceUntilIdle()

            assertNull(vm.state.photoToDelete)
            assertEquals(listOf(DETAIL.id to 2L), photos.deletes)
            assertEquals(listOf(1L, 3L), vm.savedIds())
        }

    @Test
    fun `cancelling the delete keeps the photo`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.DeletePhoto("saved-2"))

            vm.onEvent(ProductFormEvent.DismissDeletePhoto)
            advanceUntilIdle()

            assertNull(vm.state.photoToDelete)
            assertTrue(photos.deletes.isEmpty())
            assertEquals(listOf(1L, 2L, 3L), vm.savedIds())
        }

    @Test
    fun `a failed delete keeps the photo and says so`() =
        runTest(main.dispatcher) {
            photos.deleteAnswer = ApiResult.NetworkError
            val vm = editForm()
            vm.onEvent(ProductFormEvent.DeletePhoto("saved-2"))

            vm.onEvent(ProductFormEvent.ConfirmDeletePhoto)
            advanceUntilIdle()

            assertEquals(listOf(1L, 2L, 3L), vm.savedIds())
            assertEquals(UiText.Resource(R.string.product_photo_delete_failed), vm.state.photoNotice)
        }

    @Test
    fun `a photo already deleted elsewhere is simply gone`() =
        runTest(main.dispatcher) {
            photos.deleteAnswer = ApiResult.NotFound
            val vm = editForm()
            vm.onEvent(ProductFormEvent.DeletePhoto("saved-2"))

            vm.onEvent(ProductFormEvent.ConfirmDeletePhoto)
            advanceUntilIdle()

            assertEquals(listOf(1L, 3L), vm.savedIds())
            assertNull(vm.state.photoNotice)
        }

    // --- The rest of the form ---------------------------------------------

    @Test
    fun `photos are saved at once, so they are no change to throw away`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.add(prepared("satu"))
            vm.onEvent(ProductFormEvent.MovePhoto(from = 1, to = 0))
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Back)

            assertFalse(vm.state.showDiscardDialog)
            assertEquals(ProductFormResult.Left, vm.state.result)
        }
}
