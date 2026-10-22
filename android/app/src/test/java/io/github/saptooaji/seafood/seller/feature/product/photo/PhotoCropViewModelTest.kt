package io.github.saptooaji.seafood.seller.feature.product.photo

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
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

/** The crop screen (#241, boards VD-Potong-Foto): a picked photo in, a prepared photo out. */
@OptIn(ExperimentalCoroutinesApi::class)
class PhotoCropViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    @TempDir
    lateinit var cache: File

    private val preparer = FakePreparer()

    private inner class FakePreparer : PhotoPreparer {
        var size: ImageSize? = ImageSize(4000, 3000)
        var importable = true
        val imported = mutableListOf<String>()
        val prepared = mutableListOf<Triple<String, PixelRect, Int>>()
        var gate: CompletableDeferred<Unit>? = null
        var answer: PreparedPhoto? = null

        override suspend fun import(uri: String): File? {
            imported += uri
            return if (importable) File(cache, "source-${imported.size}.jpg").apply { writeBytes(byteArrayOf(1)) } else null
        }

        override suspend fun uprightSize(source: File): ImageSize? = size

        override suspend fun prepare(
            source: File,
            crop: PixelRect,
            quarterTurns: Int,
        ): PreparedPhoto {
            prepared += Triple(source.path, crop, quarterTurns)
            gate?.await()
            return answer ?: PreparedPhoto.Ready(File(cache, "ready.jpg").apply { writeBytes(byteArrayOf(2)) }, crop.width, crop.height)
        }
    }

    private val PhotoCropViewModel.state get() = uiState.value

    private fun TestScope.screen(handle: SavedStateHandle = SavedStateHandle(mapOf("source" to PICKED))): PhotoCropViewModel {
        val vm = PhotoCropViewModel(preparer, handle)
        advanceUntilIdle()
        return vm
    }

    @Test
    fun `the picked photo is copied into the cache and shown with a centred square`() =
        runTest(main.dispatcher) {
            val vm = screen()

            assertEquals(listOf(PICKED), preparer.imported)
            assertFalse(vm.state.isLoading)
            assertEquals(File(cache, "source-1.jpg").path, vm.state.sourcePath)
            assertEquals(CropAspect.Square, vm.state.crop!!.aspect)
            assertEquals(PixelRect(500, 0, 3000, 3000), vm.state.crop!!.rect())
            assertTrue(vm.state.canUse)
        }

    @Test
    fun `the aspect and the turn change the crop`() =
        runTest(main.dispatcher) {
            val vm = screen()

            vm.onEvent(PhotoCropEvent.AspectPicked(CropAspect.Wide))
            assertEquals(PixelRect(0, 0, 4000, 3000), vm.state.crop!!.rect())

            vm.onEvent(PhotoCropEvent.Rotate)
            assertEquals(1, vm.state.crop!!.quarterTurns)
            assertEquals(PixelRect(0, 875, 3000, 2250), vm.state.crop!!.rect())
        }

    @Test
    fun `zooming and panning move the crop`() =
        runTest(main.dispatcher) {
            val vm = screen()

            vm.onEvent(PhotoCropEvent.Zoom(2f))
            vm.onEvent(PhotoCropEvent.Pan(10_000f, 0f))

            assertEquals(PixelRect(2500, 750, 1500, 1500), vm.state.crop!!.rect())
        }

    @Test
    fun `using it prepares the crop with the turn, hands the photo over, and drops the copy`() =
        runTest(main.dispatcher) {
            val vm = screen()
            vm.onEvent(PhotoCropEvent.Rotate)
            val source = vm.state.sourcePath!!

            vm.onEvent(PhotoCropEvent.Use)
            advanceUntilIdle()

            assertEquals(listOf(Triple(source, PixelRect(0, 500, 3000, 3000), 1)), preparer.prepared)
            assertEquals(PhotoCropResult.Done(File(cache, "ready.jpg").path), vm.state.result)
            assertFalse(File(source).exists())
        }

    @Test
    fun `the button waits while the photo is prepared, and a second tap sends nothing`() =
        runTest(main.dispatcher) {
            preparer.gate = CompletableDeferred()
            val vm = screen()

            vm.onEvent(PhotoCropEvent.Use)
            runCurrent()
            assertTrue(vm.state.isPreparing)
            assertFalse(vm.state.canUse)
            vm.onEvent(PhotoCropEvent.Use)
            runCurrent()

            assertEquals(1, preparer.prepared.size)
        }

    @Test
    fun `a crop under 600 pixels says so and cannot be used`() =
        runTest(main.dispatcher) {
            preparer.size = ImageSize(800, 500)
            val vm = screen()

            assertTrue(vm.state.isTooSmall)
            assertFalse(vm.state.canUse)
            vm.onEvent(PhotoCropEvent.Use)
            advanceUntilIdle()
            assertTrue(preparer.prepared.isEmpty())
            assertNull(vm.state.result)
        }

    @Test
    fun `zooming in too far makes a large photo too small`() =
        runTest(main.dispatcher) {
            val vm = screen()

            vm.onEvent(PhotoCropEvent.Zoom(6f))

            assertTrue(vm.state.isTooSmall)
        }

    @Test
    fun `leaving without cropping adds nothing and drops the copy`() =
        runTest(main.dispatcher) {
            val vm = screen()
            val source = vm.state.sourcePath!!

            vm.onEvent(PhotoCropEvent.Cancel)

            assertEquals(PhotoCropResult.Cancelled, vm.state.result)
            assertFalse(File(source).exists())
            assertTrue(preparer.prepared.isEmpty())
        }

    @Test
    fun `a photo that cannot be read says so`() =
        runTest(main.dispatcher) {
            preparer.size = null
            val vm = screen()

            assertNull(vm.state.crop)
            assertEquals(UiText.Resource(R.string.photo_crop_unreadable), vm.state.error)
            assertFalse(vm.state.canUse)
        }

    @Test
    fun `a photo that cannot be copied says so`() =
        runTest(main.dispatcher) {
            preparer.importable = false
            val vm = screen()

            assertEquals(UiText.Resource(R.string.photo_crop_unreadable), vm.state.error)
        }

    @Test
    fun `a prepare that fails says so and keeps the copy to try again`() =
        runTest(main.dispatcher) {
            preparer.answer = PreparedPhoto.Unreadable
            val vm = screen()

            vm.onEvent(PhotoCropEvent.Use)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.photo_crop_unreadable), vm.state.error)
            assertNull(vm.state.result)
            assertTrue(File(vm.state.sourcePath!!).exists())
        }

    @Test
    fun `a recreated screen keeps the copy, the aspect, and the turn`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle(mapOf("source" to PICKED))
            val vm = screen(handle)
            vm.onEvent(PhotoCropEvent.AspectPicked(CropAspect.Wide))
            vm.onEvent(PhotoCropEvent.Rotate)

            val recreated = screen(handle)

            // Copied once: the picker's permission may be gone after the process died.
            assertEquals(1, preparer.imported.size)
            assertEquals(vm.state.sourcePath, recreated.state.sourcePath)
            assertEquals(CropAspect.Wide, recreated.state.crop!!.aspect)
            assertEquals(1, recreated.state.crop!!.quarterTurns)
        }

    private companion object {
        const val PICKED = "content://media/picker/0/com.android.providers.media.photopicker/media/1000"
    }
}
