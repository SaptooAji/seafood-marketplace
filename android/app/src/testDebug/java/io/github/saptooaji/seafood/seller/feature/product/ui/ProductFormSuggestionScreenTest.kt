package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.DescriptionSuggestion
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The AI description button and sheet (#240, boards VD-Produk-Form, VD-Saran-AI), driven by state only. */
@RunWith(RobolectricTestRunner::class)
class ProductFormSuggestionScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val events = mutableListOf<ProductFormEvent>()

    private fun show(state: ProductFormUiState) {
        compose.setContent { SeafoodTheme { ProductFormScreen(uiState = state, onEvent = { events += it }) } }
    }

    private val empty = ProductFormUiState(isLoading = false, categories = FakeCategoryRepository.TREE)

    /** Name, category, and condition filled: the AI can write. */
    private val ready =
        empty.copy(
            categoryId = 2,
            categoryPath = listOf("Kepiting", "Bakau"),
            attributes = FakeCategoryRepository.BAKAU.attributes,
            name = "Kepiting Bakau Super",
            condition = ProductCondition.Live,
        )

    private val suggestion =
        DescriptionSuggestion(
            description = "<b>Segar</b> dari tambak Muara Angke, 300 sampai 400 g per ekor.",
            seoDescription = "Kepiting bakau jantan grade super 300–400 g.",
        )

    @Test
    fun theButtonIsOffWithAHintUntilTheFormHasWhatTheAiNeeds() {
        show(empty)

        compose
            .onNodeWithTag(ProductFormTags.SUGGEST)
            .performScrollTo()
            .assertIsNotEnabled()
            .performClick()
        compose.onNodeWithText("Isi nama, kategori, dan kondisi dulu supaya AI bisa menyusun deskripsi.").assertExists()

        assertTrue(events.none { it == ProductFormEvent.SuggestDescription })
    }

    @Test
    fun theButtonAsksForASuggestion() {
        show(ready)

        compose.onNodeWithText("Susun deskripsi dengan AI").assertExists()
        compose.onNodeWithText("Isi nama, kategori, dan kondisi dulu supaya AI bisa menyusun deskripsi.").assertDoesNotExist()
        compose
            .onNodeWithTag(ProductFormTags.SUGGEST)
            .performScrollTo()
            .assertIsEnabled()
            .performClick()

        assertEquals(ProductFormEvent.SuggestDescription, events.last())
    }

    @Test
    fun theSheetSaysItIsWorkingWhileWaiting() {
        show(ready.copy(suggestion = SuggestionSheet.Loading))

        compose.onNodeWithText("Saran deskripsi").assertExists()
        compose.onNodeWithText("Menyusun dari nama, kategori, dan atribut produk. Bisa sampai 15 detik.").assertExists()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_USE).assertDoesNotExist()
    }

    @Test
    fun theSuggestionShowsAsPlainTextAndCanBeUsedOrCancelled() {
        show(ready.copy(suggestion = SuggestionSheet.Ready(suggestion)))

        // The markup is text: the app renders no HTML.
        compose.onNodeWithText("<b>Segar</b> dari tambak Muara Angke, 300 sampai 400 g per ekor.").assertExists()
        compose.onNodeWithText("Kepiting bakau jantan grade super 300–400 g.").assertExists()
        compose.onNodeWithText("Disusun dari isi form Anda, tanpa harga. Periksa dulu sebelum dipakai.").assertExists()
        compose.onNodeWithText("Deskripsi yang sudah Anda tulis akan diganti. Lanjutkan?").assertDoesNotExist()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_CANCEL).performScrollTo().performClick()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_USE).performScrollTo().performClick()
        compose.onNodeWithText("Pakai saran").assertExists()

        assertEquals(listOf(ProductFormEvent.DismissSuggestion, ProductFormEvent.UseSuggestion), events)
    }

    @Test
    fun replacingAWrittenDescriptionAsksInTheSheet() {
        show(ready.copy(description = "Deskripsi saya.", suggestion = SuggestionSheet.Ready(suggestion, confirmReplace = true)))

        compose.onNodeWithText("Deskripsi yang sudah Anda tulis akan diganti. Lanjutkan?").assertExists()
        compose.onNodeWithText("Ganti dengan saran").performScrollTo().performClick()

        assertEquals(ProductFormEvent.UseSuggestion, events.last())
    }

    @Test
    fun aConnectionErrorOffersToTryAgain() {
        show(ready.copy(suggestion = SuggestionSheet.Failed(UiText.Raw("Tidak ada koneksi. Periksa internet lalu coba lagi."), canRetry = true)))

        compose.onNodeWithText("Tidak ada koneksi. Periksa internet lalu coba lagi.").assertExists()
        compose.onNodeWithText("Tutup").assertExists()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_CLOSE).performClick()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_RETRY).performClick()

        assertEquals(listOf(ProductFormEvent.DismissSuggestion, ProductFormEvent.SuggestDescription), events)
    }

    @Test
    fun aRefusalOffersToWriteItOneself() {
        val message = "Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti atau tulis deskripsi sendiri."
        show(ready.copy(suggestion = SuggestionSheet.Failed(UiText.Raw(message), canRetry = false)))

        compose.onNodeWithText(message).assertExists()
        compose.onNodeWithTag(ProductFormTags.SUGGESTION_RETRY).assertDoesNotExist()
        compose.onNodeWithText("Tulis sendiri").performClick()

        assertEquals(ProductFormEvent.DismissSuggestion, events.last())
    }

    @Test
    fun aSummaryOverTheLimitShowsItsCount() {
        show(ready.copy(seoDescription = "a".repeat(200)))

        compose.onNodeWithTag(ProductFormTags.SEO).performScrollTo()
        compose.onNodeWithText("200 / 160").assertExists()
    }
}
