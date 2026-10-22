package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductPhotoRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.DETAIL
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
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
 * Photos while adding a product (#282, boards VD-Produk-Form--tambah-produk-*):
 * they wait on the phone until the product is saved, then upload to it.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ProductFormNewPhotosTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    @TempDir
    lateinit var cache: File

    private val products = FakeProductRepository()
    private val categories = FakeCategoryRepository()
    private val photos = FakeProductPhotoRepository()

    private val ProductFormViewModel.state get() = uiState.value

    private fun TestScope.addForm(handle: SavedStateHandle = SavedStateHandle()): ProductFormViewModel {
        val vm = ProductFormViewModel(products, categories, photos, handle)
        advanceUntilIdle()
        return vm
    }

    private fun prepared(name: String): String = File(cache, "$name.jpg").apply { writeBytes(byteArrayOf(1, 2, 3)) }.path

    private fun ProductFormViewModel.add(path: String) = onEvent(ProductFormEvent.PhotoPrepared(path))

    private fun ProductFormViewModel.pendingPaths() = state.photos.filterIsInstance<PendingPhoto>().map { it.path }

    private fun ProductFormViewModel.pending(path: String) = state.photos.filterIsInstance<PendingPhoto>().single { it.path == path }

    private fun ProductFormViewModel.savedIds() = state.photos.filterIsInstance<SavedPhoto>().map { it.image.id }

    private fun ProductFormViewModel.type(
        field: ProductTextField,
        value: String,
    ) = onEvent(ProductFormEvent.TextChanged(field, value))

    /** A complete ready-stock Kepiting Bakau with one variant. */
    private fun ProductFormViewModel.fill() {
        onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.BAKAU.id))
        onEvent(ProductFormEvent.AttributeChanged(FakeCategoryRepository.WEIGHT.id, "350"))
        type(ProductTextField.Name, "Kepiting Bakau Super")
        type(ProductTextField.Description, "Kepiting bakau jantan dari tambak Muara Angke.")
        onEvent(ProductFormEvent.ConditionPicked(ProductCondition.Fresh))
        type(ProductTextField.VariantName, "300–400 g per ekor")
        type(ProductTextField.VariantPrice, "185000")
    }

    // --- Before saving ----------------------------------------------------

    @Test
    fun `photos picked while adding wait on the phone`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            assertTrue(vm.state.canAddPhoto)
            val first = prepared("satu")

            vm.add(first)
            advanceUntilIdle()

            assertEquals(PendingStatus.AwaitingSave, vm.pending(first).status)
            assertTrue(photos.uploads.isEmpty())
            assertTrue(File(first).exists())
        }

    @Test
    fun `waiting photos are reordered on the phone only`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            val first = prepared("satu")
            val second = prepared("dua")
            vm.add(first)
            vm.add(second)

            vm.onEvent(ProductFormEvent.MovePhoto(from = 1, to = 0))
            advanceUntilIdle()

            assertEquals(listOf(second, first), vm.pendingPaths())
            assertTrue(photos.reorders.isEmpty())
        }

    @Test
    fun `a waiting photo has the same actions, and its delete sends nothing`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            val first = prepared("satu")
            val second = prepared("dua")
            vm.add(first)
            vm.add(second)
            val key = vm.pending(second).key

            vm.onEvent(ProductFormEvent.OpenPhotoActions(key))
            assertEquals(key, vm.state.photoActions)
            vm.onEvent(ProductFormEvent.MakeMainPhoto(key))
            assertEquals(listOf(second, first), vm.pendingPaths())

            vm.onEvent(ProductFormEvent.DeletePhoto(key))
            vm.onEvent(ProductFormEvent.ConfirmDeletePhoto)
            advanceUntilIdle()

            assertEquals(listOf(first), vm.pendingPaths())
            assertFalse(File(second).exists())
            assertTrue(photos.deletes.isEmpty())
            assertTrue(photos.reorders.isEmpty())
        }

    @Test
    fun `eight waiting photos turn adding off`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            (1..8).forEach { vm.add(prepared("foto-$it")) }

            assertFalse(vm.state.canAddPhoto)
        }

    @Test
    fun `waiting photos survive the form being recreated`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            val vm = addForm(handle)
            val first = prepared("satu")
            val second = prepared("dua")
            vm.add(first)
            vm.add(second)
            vm.onEvent(ProductFormEvent.MovePhoto(from = 1, to = 0))

            val recreated = addForm(handle)

            assertEquals(listOf(second, first), recreated.pendingPaths())
            assertTrue(recreated.state.photos.all { (it as PendingPhoto).status == PendingStatus.AwaitingSave })
        }

    @Test
    fun `a waiting photo is a change, and throwing the form away deletes it`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            val first = prepared("satu")
            vm.add(first)

            vm.onEvent(ProductFormEvent.Back)
            assertTrue(vm.state.showDiscardDialog)
            vm.onEvent(ProductFormEvent.ConfirmDiscard)

            assertEquals(ProductFormResult.Left, vm.state.result)
            assertFalse(File(first).exists())
        }

    // --- Saving -----------------------------------------------------------

    @Test
    fun `saving creates the product, then uploads the photos to it in their order`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fill()
            val first = prepared("satu")
            val second = prepared("dua")
            vm.add(first)
            vm.add(second)
            vm.onEvent(ProductFormEvent.MovePhoto(from = 1, to = 0))

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(1, products.createCalls.size)
            assertEquals(listOf(second, first), photos.uploads.map { it.path })
            assertEquals(setOf(DETAIL.id), photos.uploads.map { it.productId }.toSet())
            assertEquals(1, photos.mostAtOnce)
            // Uploaded in order, so the server's order is already right.
            assertTrue(photos.reorders.isEmpty())
            assertEquals(ProductFormResult.Saved, vm.state.result)
            assertFalse(File(first).exists() || File(second).exists())
        }

    @Test
    fun `a product without photos saves as before`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(photos.uploads.isEmpty())
            assertEquals(ProductFormResult.Saved, vm.state.result)
        }

    @Test
    fun `the save button counts the photos while they upload, and the form is locked`() =
        runTest(main.dispatcher) {
            photos.uploadGates[1] = CompletableDeferred()
            val vm = addForm()
            vm.fill()
            vm.add(prepared("satu"))
            vm.add(prepared("dua"))

            vm.onEvent(ProductFormEvent.Save)
            runCurrent()

            assertEquals(2, vm.state.afterSaveTotal)
            assertEquals(1, vm.state.afterSaveCurrent)
            assertTrue(vm.state.isBusy)
            vm.onEvent(ProductFormEvent.Save)
            runCurrent()
            assertEquals(1, products.createCalls.size)
            assertNull(vm.state.result)
        }

    @Test
    fun `a refused product uploads nothing and keeps its photos waiting`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.Invalid(mapOf("name" to listOf("Nama sudah dipakai.")), "Nama sudah dipakai.")
            val vm = addForm()
            vm.fill()
            val first = prepared("satu")
            vm.add(first)

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(photos.uploads.isEmpty())
            assertEquals(PendingStatus.AwaitingSave, vm.pending(first).status)
            assertTrue(File(first).exists())
            assertFalse(vm.state.isEdit)
            assertNull(vm.state.result)
        }

    @Test
    fun `no connection on saving keeps the photos waiting`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.NetworkError
            val vm = addForm()
            vm.fill()
            val first = prepared("satu")
            vm.add(first)

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(photos.uploads.isEmpty())
            assertEquals(PendingStatus.AwaitingSave, vm.pending(first).status)
        }

    // --- Saved, but a photo is not -----------------------------------------

    @Test
    fun `a refused photo turns the form into the saved product's edit form`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[2] =
                ApiResult.Invalid(mapOf("photo" to listOf("Foto terlalu memanjang.")), "Foto terlalu memanjang.")
            val vm = addForm()
            vm.fill()
            val first = prepared("satu")
            val second = prepared("dua")
            vm.add(first)
            vm.add(second)

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertNull(vm.state.result)
            assertTrue(vm.state.isEdit)
            assertFalse(vm.state.isBusy)
            assertNull(vm.state.afterSaveTotal)
            assertEquals(listOf(101L), vm.savedIds())
            assertEquals(PendingStatus.Failed(UiText.Raw("Foto terlalu memanjang.")), vm.pending(second).status)
            assertEquals(UiText.Resource(R.string.product_photo_saved_partly, listOf(1)), vm.state.photoNotice)
        }

    @Test
    fun `a photo tried again goes to the saved product`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.NetworkError
            val vm = addForm()
            vm.fill()
            val first = prepared("satu")
            vm.add(first)
            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.RetryPhoto(vm.pending(first).key))
            advanceUntilIdle()

            assertEquals(listOf(DETAIL.id, DETAIL.id), photos.uploads.map { it.productId })
            assertEquals(listOf(102L), vm.savedIds())
        }

    @Test
    fun `too many uploads after saving keep the queue's own message`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.TooManyRequests(60, "Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi.")
            val vm = addForm()
            vm.fill()
            vm.add(prepared("satu"))
            vm.add(prepared("dua"))

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(vm.state.isEdit)
            assertEquals(1, photos.uploads.size)
            assertEquals(UiText.Resource(R.string.product_photo_paused, listOf<Any>(2, 1L)), vm.state.photoNotice)
        }

    @Test
    fun `leaving the saved product refreshes the list, without asking`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.NetworkError
            val vm = addForm()
            vm.fill()
            vm.add(prepared("satu"))
            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Back)

            assertFalse(vm.state.showDiscardDialog)
            assertEquals(ProductFormResult.Saved, vm.state.result)
        }

    @Test
    fun `a recreated form after that opens the saved product`() =
        runTest(main.dispatcher) {
            photos.uploadAnswers[1] = ApiResult.NetworkError
            val handle = SavedStateHandle()
            val vm = addForm(handle)
            vm.fill()
            vm.add(prepared("satu"))
            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            val recreated = addForm(handle)

            assertTrue(recreated.state.isEdit)
            assertEquals(listOf(DETAIL.id), products.getCalls)
        }
}
