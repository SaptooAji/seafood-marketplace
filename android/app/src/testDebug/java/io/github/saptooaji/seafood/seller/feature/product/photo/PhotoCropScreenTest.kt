package io.github.saptooaji.seafood.seller.feature.product.photo

import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithContentDescription
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The crop screen as the boards VD-Potong-Foto draw it, driven by state only. */
@RunWith(RobolectricTestRunner::class)
class PhotoCropScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val events = mutableListOf<PhotoCropEvent>()

    private fun show(state: PhotoCropUiState) {
        compose.setContent { SeafoodTheme { PhotoCropScreen(uiState = state, onEvent = { events += it }) } }
    }

    private val ready = PhotoCropUiState(isLoading = false, sourcePath = "/cache/photos/source.jpg", crop = CropState.of(4000, 3000))

    @Test
    fun theAspectsTheTurnAndTheUseButtonSendTheirEvents() {
        show(ready)

        compose.onNodeWithText("Potong foto").assertExists()
        compose.onNodeWithText("Sisi terpendek minimal 600 piksel. Geser dan cubit untuk mengatur.").assertExists()
        compose.onNodeWithTag(PhotoCropTags.aspect(CropAspect.Wide)).performClick()
        compose.onNodeWithContentDescription("Putar").performClick()
        compose.onNodeWithTag(PhotoCropTags.USE).assertIsEnabled().performClick()

        assertEquals(listOf(PhotoCropEvent.AspectPicked(CropAspect.Wide), PhotoCropEvent.Rotate, PhotoCropEvent.Use), events)
    }

    @Test
    fun theAspectsAreNamedAsOnTheBoard() {
        show(ready)

        compose.onNodeWithText("Persegi 1:1").assertExists()
        compose.onNodeWithText("Lebar 4:3").assertExists()
    }

    @Test
    fun closingCancels() {
        show(ready)

        compose.onNodeWithContentDescription("Batal").performClick()

        assertEquals(PhotoCropEvent.Cancel, events.last())
    }

    @Test
    fun aCropThatIsTooSmallSaysSoAndCannotBeUsed() {
        show(ready.copy(crop = CropState.of(800, 500)))

        compose
            .onNodeWithText(
                "Foto terlalu kecil: sisi terpendek minimal 600 piksel. Pilih foto lain atau ambil ulang dari dekat.",
            ).assertExists()
        compose.onNodeWithTag(PhotoCropTags.USE).assertIsNotEnabled().performClick()

        assertTrue(events.none { it == PhotoCropEvent.Use })
    }

    @Test
    fun theButtonWaitsWhileThePhotoIsPrepared() {
        show(ready.copy(isPreparing = true))

        compose.onNodeWithTag(PhotoCropTags.USE).assertIsNotEnabled()
        compose.onNodeWithText("Menyiapkan foto…").assertExists()
    }

    @Test
    fun aPhotoThatCannotBeReadSaysSo() {
        show(PhotoCropUiState(isLoading = false, error = UiText.Resource(R.string.photo_crop_unreadable)))

        compose.onNodeWithText("Foto tidak bisa dibuka. Pilih foto lain.").assertExists()
        compose.onNodeWithTag(PhotoCropTags.USE).assertIsNotEnabled()
    }
}
