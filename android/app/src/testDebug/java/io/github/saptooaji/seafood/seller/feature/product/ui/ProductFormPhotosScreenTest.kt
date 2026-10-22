package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.ui.test.assertCountEquals
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onAllNodesWithText
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The photo section of the edit form and its sheets (#241, boards VD-Produk-Form--foto-*), driven by state only. */
@RunWith(RobolectricTestRunner::class)
class ProductFormPhotosScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val events = mutableListOf<ProductFormEvent>()
    private val sources = mutableListOf<PhotoSource>()

    private fun show(state: ProductFormUiState) {
        compose.setContent {
            SeafoodTheme { ProductFormScreen(uiState = state, onEvent = { events += it }, onAddPhoto = { sources += it }) }
        }
    }

    private fun saved(id: Long) = SavedPhoto(ProductImage(id, "http://localhost/$id.webp", "http://localhost/${id}_t.webp"))

    private val editing =
        ProductFormUiState(
            isEdit = true,
            isLoading = false,
            categories = FakeCategoryRepository.TREE,
            name = "Kepiting Bakau Super",
            photos = listOf(saved(1), saved(2), saved(3)),
        )

    @Test
    fun theFirstPhotoIsTheMainOneAndPhotosSaveAtOnce() {
        show(editing)

        compose.onNodeWithTag(ProductFormTags.photo("saved-1")).performScrollTo()
        compose.onAllNodesWithText("Utama").assertCountEquals(1)
        compose
            .onNodeWithText(
                "3 dari 8 foto, langsung tersimpan. Foto pertama tampil di katalog; tahan lalu geser untuk mengurutkan.",
            ).assertExists()
    }

    @Test
    fun addingAsksForTheSource() {
        show(editing)

        compose.onNodeWithTag(ProductFormTags.ADD_PHOTO).performScrollTo().performClick()
        compose.onNodeWithText("Ambil dari kamera").assertExists()
        compose.onNodeWithTag(ProductFormTags.PHOTO_FROM_GALLERY).performClick()

        assertEquals(listOf(PhotoSource.Gallery), sources)
        compose.onNodeWithText("Ambil dari kamera").assertDoesNotExist()
    }

    @Test
    fun theCameraIsTheOtherSource() {
        show(editing)

        compose.onNodeWithTag(ProductFormTags.ADD_PHOTO).performScrollTo().performClick()
        compose.onNodeWithTag(ProductFormTags.PHOTO_FROM_CAMERA).performClick()

        assertEquals(listOf(PhotoSource.Camera), sources)
    }

    @Test
    fun eightPhotosTurnAddingOffAndSayWhy() {
        show(editing.copy(photos = (1L..8L).map { saved(it) }))

        compose
            .onNodeWithTag(ProductFormTags.ADD_PHOTO)
            .performScrollTo()
            .assertIsNotEnabled()
            .performClick()
        compose.onNodeWithText("8 dari 8 foto. Hapus satu untuk menambah yang baru.").assertExists()
        assertTrue(sources.isEmpty())
        compose.onNodeWithText("Ambil dari kamera").assertDoesNotExist()
    }

    @Test
    fun anUploadShowsItsProgressAndTheNextOneWaits() {
        show(
            editing.copy(
                photos =
                    listOf(
                        saved(1),
                        PendingPhoto("pending-1", "/cache/photos/a.jpg", PendingStatus.Uploading(0.64f)),
                        PendingPhoto("pending-2", "/cache/photos/b.jpg", PendingStatus.Queued),
                    ),
            ),
        )

        compose.onNodeWithTag(ProductFormTags.photo("pending-1")).performScrollTo()
        compose.onNodeWithText("64%").assertExists()
        compose.onNodeWithText("Menunggu").assertExists()
    }

    @Test
    fun aRefusedPhotoSaysWhyAndCanBeTriedAgainOrTakenOff() {
        show(
            editing.copy(
                photos =
                    listOf(
                        saved(1),
                        PendingPhoto(
                            "pending-1",
                            "/cache/photos/a.jpg",
                            PendingStatus.Failed(UiText.Raw("Produk paling banyak memiliki 8 foto.")),
                        ),
                    ),
            ),
        )

        compose.onNodeWithText("Foto ke-2 gagal diunggah: Produk paling banyak memiliki 8 foto.").performScrollTo()
        compose.onNodeWithTag(ProductFormTags.photoRetry("pending-1")).performScrollTo().performClick()
        compose.onNodeWithTag(ProductFormTags.photoRemove("pending-1")).performScrollTo().performClick()

        assertEquals(listOf(ProductFormEvent.RetryPhoto("pending-1"), ProductFormEvent.RemovePendingPhoto("pending-1")), events)
    }

    @Test
    fun aStoppedQueueSaysWhyAndGoesOnWhenAsked() {
        val notice = "Terlalu sering mengunggah foto. 2 foto ditunda; coba lagi dalam 1 menit."
        show(
            editing.copy(
                photos =
                    listOf(
                        saved(1),
                        PendingPhoto("pending-1", "/cache/photos/a.jpg", PendingStatus.Paused),
                        PendingPhoto("pending-2", "/cache/photos/b.jpg", PendingStatus.Paused),
                    ),
                photoNotice = UiText.Raw(notice),
            ),
        )

        compose.onNodeWithText(notice).performScrollTo()
        compose.onAllNodesWithText("Tertunda").assertCountEquals(2)
        compose.onNodeWithTag(ProductFormTags.PHOTO_RESUME).performScrollTo().performClick()

        assertEquals(ProductFormEvent.ResumeUploads, events.last())
    }

    @Test
    fun aFailedReorderSaysSoWithoutAnAction() {
        show(editing.copy(photoNotice = UiText.Raw("Urutan foto belum tersimpan, jadi dikembalikan seperti semula.")))

        compose.onNodeWithText("Urutan foto belum tersimpan, jadi dikembalikan seperti semula.").performScrollTo()
        compose.onNodeWithTag(ProductFormTags.PHOTO_RESUME).assertDoesNotExist()
    }

    @Test
    fun tappingAPhotoOpensItsActions() {
        show(editing)

        compose.onNodeWithTag(ProductFormTags.photo("saved-2")).performScrollTo().performClick()

        assertEquals(ProductFormEvent.OpenPhotoActions("saved-2"), events.last())
    }

    @Test
    fun aPhotoCanBecomeTheMainOneOrBeDeleted() {
        show(editing.copy(photoActions = "saved-2"))

        compose.onNodeWithText("Foto 2 dari 3").assertExists()
        compose.onNodeWithTag(ProductFormTags.PHOTO_MAKE_MAIN).performClick()
        compose.onNodeWithTag(ProductFormTags.PHOTO_DELETE).performClick()

        assertEquals(listOf(ProductFormEvent.MakeMainPhoto("saved-2"), ProductFormEvent.DeletePhoto("saved-2")), events)
    }

    @Test
    fun theMainPhotoCannotBeMadeMainAgain() {
        show(editing.copy(photoActions = "saved-1"))

        compose.onNodeWithTag(ProductFormTags.PHOTO_MAKE_MAIN).assertDoesNotExist()
        compose.onNodeWithTag(ProductFormTags.PHOTO_DELETE).assertExists()
    }

    @Test
    fun deletingAPhotoAsksFirst() {
        show(editing.copy(photoToDelete = "saved-2"))

        compose.onNodeWithText("Hapus foto ini?").assertExists()
        compose.onNodeWithText("Foto langsung hilang dari produk dan katalog pembeli.").assertExists()
        compose.onNodeWithTag(ProductFormTags.CANCEL_DELETE_PHOTO).performClick()
        compose.onNodeWithTag(ProductFormTags.CONFIRM_DELETE_PHOTO).performClick()

        assertEquals(listOf(ProductFormEvent.DismissDeletePhoto, ProductFormEvent.ConfirmDeletePhoto), events)
    }

    // --- A new product (#282) ---------------------------------------------

    private val adding = ProductFormUiState(isLoading = false, categories = FakeCategoryRepository.TREE)

    private fun waiting(n: Int) = PendingPhoto("pending-$n", "/cache/photos/$n.jpg", PendingStatus.AwaitingSave)

    @Test
    fun aNewProductTakesPhotosThatWaitForTheSave() {
        show(adding.copy(photos = listOf(waiting(1), waiting(2))))

        compose.onNodeWithTag(ProductFormTags.photo("pending-1")).performScrollTo()
        compose.onAllNodesWithText("Utama").assertCountEquals(1)
        compose.onNodeWithText("2 dari 8 foto. Diunggah setelah produk disimpan; tahan lalu geser untuk mengurutkan.").assertExists()
        compose.onNodeWithText("Menunggu").assertDoesNotExist()
        compose.onNodeWithTag(ProductFormTags.ADD_PHOTO).performScrollTo().performClick()
        compose.onNodeWithTag(ProductFormTags.PHOTO_FROM_GALLERY).performClick()

        assertEquals(listOf(PhotoSource.Gallery), sources)
    }

    @Test
    fun aWaitingPhotoOpensItsActions() {
        show(adding.copy(photos = listOf(waiting(1), waiting(2))))

        compose.onNodeWithTag(ProductFormTags.photo("pending-2")).performScrollTo().performClick()

        assertEquals(ProductFormEvent.OpenPhotoActions("pending-2"), events.last())
    }

    @Test
    fun aWaitingPhotoCanBecomeTheMainOne() {
        show(adding.copy(photos = listOf(waiting(1), waiting(2)), photoActions = "pending-2"))

        compose.onNodeWithText("Foto 2 dari 2").assertExists()
        compose.onNodeWithTag(ProductFormTags.PHOTO_MAKE_MAIN).performClick()

        assertEquals(ProductFormEvent.MakeMainPhoto("pending-2"), events.last())
    }

    @Test
    fun aNewProductWithoutPhotosSaysOneIsSuggested() {
        show(adding)

        compose.onNodeWithText("Belum ada foto. Minimal satu foto disarankan.").performScrollTo()
        compose.onNodeWithTag(ProductFormTags.ADD_PHOTO).assertIsEnabled()
    }

    @Test
    fun theSaveButtonCountsThePhotosWhileTheyUpload() {
        show(
            adding.copy(
                isSaving = true,
                afterSaveTotal = 2,
                photos =
                    listOf(
                        PendingPhoto("pending-1", "/cache/photos/1.jpg", PendingStatus.Uploading(0.5f)),
                        PendingPhoto("pending-2", "/cache/photos/2.jpg", PendingStatus.Queued),
                    ),
            ),
        )

        compose.onNodeWithTag(ProductFormTags.SAVE).assertIsNotEnabled()
        compose.onNodeWithText("Mengunggah foto 1 dari 2").assertExists()
    }

    @Test
    fun aSavedProductWithAFailedPhotoSaysItIsSaved() {
        show(
            editing.copy(
                photos =
                    listOf(
                        saved(101),
                        PendingPhoto("pending-2", "/cache/photos/2.jpg", PendingStatus.Failed(UiText.Raw("Foto terlalu memanjang."))),
                    ),
                photoNotice = UiText.Raw("Produk tersimpan. 1 foto belum terunggah."),
            ),
        )

        compose.onNodeWithText("Produk tersimpan. 1 foto belum terunggah.").performScrollTo()
        compose.onNodeWithText("Foto ke-2 gagal diunggah: Foto terlalu memanjang.").assertExists()
    }
}
