package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KEPITING_300
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.movement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.time.LocalDate
import java.time.ZoneId

/** The stock book and its sheets as the boards VD-Buku-Stok and VD-Catat-Stok draw them, driven by state only (#244). */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class StockBookScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<StockBookEvent>()
    private var wentBack = false

    private val stockIn =
        movement(40).copy(typeLabel = "Stok Masuk", supplierName = "Pak Darto", unitCost = 140_000, note = "<i>pagi</i>")
    private val loaded =
        StockBookUiState(
            isLoading = false,
            movements = listOf(stockIn),
            products = FakeStockRepository.SCREEN,
            today = LocalDate.of(2026, 10, 18),
            zone = ZoneId.of("Asia/Jakarta"),
        )

    private fun show(state: StockBookUiState) {
        compose.setContent {
            SeafoodTheme { StockBookScreen(uiState = state, onEvent = { events += it }, onBack = { wentBack = true }) }
        }
    }

    @Test
    fun aLineShowsWhatHappenedWithItsDetailsAsText() {
        show(loaded)

        compose.onNodeWithText(context.getString(R.string.stock_book_today)).assertIsDisplayed()
        compose
            .onNodeWithTag(StockBookTags.row(40))
            .assertTextContains("Stok Masuk", substring = true)
            .assertTextContains("Kepiting Bakau Super, 300–400 g per ekor", substring = true)
            .assertTextContains("+5 kg", substring = true)
            .assertTextContains("jadi 12,5 kg", substring = true)
            .assertTextContains("Pak Darto, Rp140.000/kg, <i>pagi</i>, Budi, 05.10", substring = true)
    }

    @Test
    fun theChipsOpenTheFilterAndBackLeaves() {
        show(loaded)

        compose.onNodeWithTag(StockBookTags.TYPE_CHIP).assertTextContains(context.getString(R.string.stock_book_all_types)).performClick()
        compose.onNodeWithTag(StockBookTags.BACK).performClick()

        assertEquals(StockBookEvent.OpenFilter, events.last())
        assertTrue(wentBack)
    }

    @Test
    fun theButtonOpensTheRecordSheet() {
        show(loaded)

        compose.onNodeWithTag(StockBookTags.ADD).performClick()

        assertEquals(StockBookEvent.OpenRecord, events.last())
    }

    @Test
    fun aFilterWithoutLinesOffersToClearIt() {
        show(loaded.copy(movements = emptyList(), filter = StockFilter(type = StockMovementType.Shrinkage)))

        compose.onNodeWithText(context.getString(R.string.stock_book_empty_filtered)).assertIsDisplayed()
        compose.onNodeWithTag(StockBookTags.CLEAR_FILTER).performClick()

        assertEquals(StockBookEvent.ClearFilter, events.last())
    }

    @Test
    fun anEmptyBookSaysSo() {
        show(loaded.copy(movements = emptyList()))

        compose.onNodeWithText(context.getString(R.string.stock_book_empty)).assertIsDisplayed()
        compose.onNodeWithTag(StockBookTags.CLEAR_FILTER).assertDoesNotExist()
    }

    @Test
    fun theFilterSheetListsEveryTypeAndRefusesABackwardRange() {
        show(loaded.copy(filterSheet = FilterDraft(from = LocalDate.of(2026, 10, 7), to = LocalDate.of(2026, 10, 1))))

        StockMovementType.entries.forEach { compose.onNodeWithTag(StockBookTags.filterType(it)).performScrollTo().assertIsDisplayed() }
        // The sheet swallows taps, so its plain texts merge into it; they are found unmerged.
        compose.onNodeWithText(context.getString(R.string.stock_book_error_dates), useUnmergedTree = true).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(StockBookTags.APPLY_FILTER).performScrollTo().assertIsNotEnabled()
    }

    @Test
    fun theRecordSheetOffersOnlyTheFourTypesASellerWrites() {
        show(loaded.copy(record = RecordSheet(variantId = KEPITING_300.id)))

        compose.onNodeWithTag(StockBookTags.recordType(StockMovementType.StockIn)).assertIsDisplayed()
        compose.onNodeWithTag(StockBookTags.recordType(StockMovementType.Adjustment)).assertIsDisplayed()
        compose.onNodeWithTag(StockBookTags.recordType(StockMovementType.SaleApp)).assertDoesNotExist()
        compose.onNodeWithTag(StockBookTags.recordType(StockMovementType.CancelReturn)).assertDoesNotExist()
        compose.onNodeWithTag(StockBookTags.RECORD_VARIANT).assertTextContains("Kepiting Bakau Super, 300–400 g per ekor", substring = true)
        compose.onNodeWithTag(StockBookTags.RECORD_SUPPLIER).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun onlyStockInAsksForTheSupplierAndCost() {
        show(loaded.copy(record = RecordSheet(type = StockMovementType.Shrinkage)))

        compose.onNodeWithTag(StockBookTags.RECORD_SUPPLIER).assertDoesNotExist()
        compose.onNodeWithTag(StockBookTags.RECORD_COST).assertDoesNotExist()
        compose.onNodeWithTag(StockBookTags.RECORD_NOTE).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aRefusedQuantityShowsUnderItsField() {
        show(
            loaded.copy(
                record =
                    RecordSheet(
                        type = StockMovementType.Shrinkage,
                        variantId = KEPITING_300.id,
                        qty = "15",
                        errors = mapOf(RecordField.Qty to UiText.Raw("Stok tidak cukup: sekarang 12 kg.")),
                    ),
            ),
        )

        compose.onNodeWithText("Stok tidak cukup: sekarang 12 kg.", useUnmergedTree = true).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theVariantPickerListsTheActiveVariantsWithTheirStock() {
        show(loaded.copy(record = RecordSheet(pickingVariant = true)))

        compose.onNodeWithTag(StockBookTags.pickVariant(KEPITING_300.id)).assertTextContains("12 kg", substring = true).performClick()

        assertEquals(StockBookEvent.RecordVariantPicked(KEPITING_300.id), events.last())
    }
}
