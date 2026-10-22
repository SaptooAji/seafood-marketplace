package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.variant
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The variant rows and sheets of the edit form (boards VD-Produk-Form, VD-Varian), driven by state only. */
@RunWith(RobolectricTestRunner::class)
class ProductFormVariantsScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val events = mutableListOf<ProductFormEvent>()

    private fun show(state: ProductFormUiState) {
        compose.setContent { SeafoodTheme { ProductFormScreen(uiState = state, onEvent = { events += it }) } }
    }

    private val editing =
        ProductFormUiState(
            isEdit = true,
            isLoading = false,
            categories = FakeCategoryRepository.TREE,
            name = "Kepiting Bakau Super",
            variants = listOf(variant(id = 1, price = 185_000), variant(id = 2, price = 215_000)),
        )

    @Test
    fun aRowShowsTheVariantAndOpensItsSheet() {
        show(editing)

        compose.onNodeWithTag(ProductFormTags.variantRow(1)).performScrollTo()
        compose.onNodeWithText("Ukuran 1").assertIsDisplayed()
        compose.onNodeWithText("Rp185.000").assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.variantRow(1)).assertTextContains("Stok 18,5 kg, min 1 kg")
        compose.onNodeWithTag(ProductFormTags.variantRow(1)).performClick()

        assertEquals(ProductFormEvent.EditVariant(1), events.last())
    }

    @Test
    fun aDozenShowsTheServerLabel() {
        show(editing.copy(variants = listOf(variant(id = 1, unitLabel = "Lusin", unit = VariantUnit.Dozen))))

        compose.onNodeWithText("Stok 18,5 lusin, min 1 lusin", substring = true).assertExists()
    }

    @Test
    fun aVariantSwitchedOffSaysItIsNotSold() {
        show(editing.copy(variants = listOf(variant(id = 1), variant(id = 2, isActive = false))))

        compose.onNodeWithTag(ProductFormTags.variantRow(2)).performScrollTo()
        compose.onNodeWithText("Tidak dijual", substring = true).assertExists()
    }

    @Test
    fun eachBusyRowShowsWhatItIsDoing() {
        show(editing.copy(variantBusy = mapOf(1L to VariantBusy.Saving, 2L to VariantBusy.Deleting)))

        compose.onNodeWithTag(ProductFormTags.variantRow(1)).performScrollTo()
        compose.onNodeWithText("Menyimpan…").assertExists()
        compose.onNodeWithText("Menghapus…").assertExists()
    }

    @Test
    fun theAddButtonOpensTheAddSheet() {
        show(editing)

        compose
            .onNodeWithTag(ProductFormTags.ADD_VARIANT)
            .performScrollTo()
            .assertIsEnabled()
            .performClick()

        assertEquals(ProductFormEvent.AddVariant, events.last())
    }

    @Test
    fun twentyVariantsTurnTheAddButtonOffAndSayWhy() {
        show(editing.copy(variants = (1L..20L).map { variant(id = it) }))

        compose.onNodeWithTag(ProductFormTags.ADD_VARIANT).performScrollTo().assertIsNotEnabled()
        compose.onNodeWithText("Maksimal 20 ukuran per produk. Hapus satu untuk menambah yang baru.").assertExists()
        compose.onNodeWithText("20 dari 20").assertExists()
    }

    @Test
    fun theAddSheetAsksForTheOpeningStockAndSaves() {
        show(editing.copy(variantSheet = VariantSheet()))

        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_NAME).performTextInput("1 kg")
        compose.onNodeWithTag(ProductFormTags.sheetUnit(VariantUnit.Dozen)).performClick()
        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_STOCK).performScrollTo().performTextInput("10")
        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_DELETE).assertDoesNotExist()
        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_SAVE).performScrollTo().performClick()

        assertTrue(events.contains(ProductFormEvent.VariantSheetTextChanged(VariantField.Name, "1 kg")))
        assertTrue(events.contains(ProductFormEvent.VariantSheetUnitPicked(VariantUnit.Dozen)))
        assertTrue(events.contains(ProductFormEvent.VariantSheetTextChanged(VariantField.InitialStock, "10")))
        assertEquals(ProductFormEvent.SaveVariant, events.last())
    }

    @Test
    fun theEditSheetShowsTheStockWithoutChangingIt() {
        show(editing.copy(variantSheet = VariantSheet(variantId = 1, name = "Ukuran 1", price = "185000")))

        compose.onNodeWithText("Ubah ukuran").assertExists()
        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_STOCK).assertDoesNotExist()
        compose.onNodeWithText("Diubah lewat Stok hari ini atau Buku stok", substring = true).assertExists()
        compose.onNodeWithTag(ProductFormTags.VARIANT_SHEET_ACTIVE).performScrollTo().performClick()
        compose
            .onNodeWithTag(ProductFormTags.VARIANT_SHEET_DELETE)
            .performScrollTo()
            .assertIsEnabled()
            .performClick()

        assertTrue(events.contains(ProductFormEvent.VariantSheetActiveChanged(false)))
        assertEquals(ProductFormEvent.DeleteVariant, events.last())
    }

    @Test
    fun theLastVariantCannotBeDeletedFromItsSheet() {
        show(editing.copy(variants = listOf(variant(id = 1)), variantSheet = VariantSheet(variantId = 1, name = "Ukuran 1")))

        compose
            .onNodeWithTag(ProductFormTags.VARIANT_SHEET_DELETE)
            .performScrollTo()
            .assertIsNotEnabled()
            .performClick()

        assertFalse(events.contains(ProductFormEvent.DeleteVariant))
    }

    @Test
    fun aRefusedFieldShowsItsErrorInTheSheet() {
        show(
            editing.copy(
                variantSheet = VariantSheet(name = "1 kg", errors = mapOf(VariantField.Name to UiText.Raw("Nama ukuran sudah dipakai."))),
            ),
        )

        compose.onNodeWithText("Nama ukuran sudah dipakai.").assertExists()
    }

    @Test
    fun deletingAsksWithTheVariantNameAndKeepsTheStockHistory() {
        show(editing.copy(variantToDelete = variant(id = 2, name = "300–400 g per ekor")))

        compose.onNodeWithText("Hapus ukuran 300–400 g per ekor?").assertExists()
        compose.onNodeWithText("Ukuran ini tidak bisa dibeli lagi. Riwayat stoknya tetap tersimpan.").assertExists()
        compose.onNodeWithTag(ProductFormTags.CANCEL_DELETE_VARIANT).performClick()
        compose.onNodeWithTag(ProductFormTags.CONFIRM_DELETE_VARIANT).performClick()

        assertEquals(listOf(ProductFormEvent.DismissDeleteVariant, ProductFormEvent.ConfirmDeleteVariant), events)
    }

    @Test
    fun theServerNoticeShowsInTheVariantSection() {
        val message = "Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual."
        show(editing.copy(variantNotice = UiText.Raw(message)))

        compose.onNodeWithText(message).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theMessageAfterASaveShowsAndIsMarkedShown() {
        show(editing.copy(variantMessage = UiText.Raw("Ukuran Jumbo 1 kg up ditambahkan.")))

        compose.onNodeWithText("Ukuran Jumbo 1 kg up ditambahkan.").assertExists()
        compose.mainClock.advanceTimeBy(MESSAGE_TIMEOUT_MILLIS)
        compose.waitForIdle()

        assertTrue(events.contains(ProductFormEvent.VariantMessageShown))
    }

    private companion object {
        /** Longer than the screen shows the message for. */
        const val MESSAGE_TIMEOUT_MILLIS = 5_000L
    }
}
