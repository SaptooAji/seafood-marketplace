package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextReplacement
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KEPITING_300
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.RAJUNGAN_SUPER
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.time.ZoneId
import java.time.ZonedDateTime

/** "Stok hari ini" as the boards VD-Stok-Hari-Ini and its states draw it, driven by state only (#243). */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class StockTodayScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<StockTodayEvent>()
    private var openedProducts = false
    private var openedBook = false

    /** 07.30 WIB on Sunday 18 October 2026. */
    private val now = ZonedDateTime.of(2026, 10, 18, 7, 30, 0, 0, ZoneId.of("Asia/Jakarta"))
    private val loaded = StockTodayUiState(isLoading = false, products = FakeStockRepository.SCREEN, now = now)

    private fun show(state: StockTodayUiState) {
        compose.setContent {
            SeafoodTheme {
                StockTodayScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onOpenProducts = { openedProducts = true },
                    onOpenBook = { openedBook = true },
                )
            }
        }
    }

    @Test
    fun theDayAndEveryVariantWithWhenItWasCounted() {
        show(loaded)

        compose.onNodeWithText("Minggu, 18 Oktober").assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.stock_summary_left, 2)).assertIsDisplayed()
        compose.onNodeWithText("2/4").assertIsDisplayed()
        compose.onNodeWithText("Kepiting Bakau Super").assertIsDisplayed()
        compose.onNodeWithTag(StockTodayTags.row(KEPITING_300.id)).assertTextContains("Dicek 05.40", substring = true)
        compose
            .onNodeWithTag(StockTodayTags.row(RAJUNGAN_SUPER.id))
            .performScrollTo()
            .assertTextContains(context.getString(R.string.stock_never_checked), substring = true)
            .assertTextContains(context.getString(R.string.stock_stale_badge), substring = true)
    }

    @Test
    fun theSummaryOpensTheStockBook() {
        show(loaded)

        compose.onNodeWithTag(StockTodayTags.OPEN_BOOK).assertTextContains(context.getString(R.string.stock_open_book)).performClick()

        assertTrue(openedBook)
    }

    @Test
    fun plusMinusAndTypingSendTheirVariant() {
        show(loaded)

        compose.onNodeWithTag(StockTodayTags.increase(KEPITING_300.id)).performClick()
        compose.onNodeWithTag(StockTodayTags.decrease(KEPITING_300.id)).performClick()
        compose.onNodeWithTag(StockTodayTags.field(KEPITING_300.id)).performTextReplacement("7,5")

        assertEquals(
            listOf(
                StockTodayEvent.Increase(KEPITING_300.id),
                StockTodayEvent.Decrease(KEPITING_300.id),
                StockTodayEvent.Typed(KEPITING_300.id, "7,5"),
            ),
            events,
        )
    }

    @Test
    fun minusIsOffAtZero() {
        show(loaded)

        compose.onNodeWithTag(StockTodayTags.decrease(RAJUNGAN_SUPER.id)).performScrollTo().assertIsNotEnabled()
        compose.onNodeWithTag(StockTodayTags.decrease(KEPITING_300.id)).assertIsEnabled()
    }

    @Test
    fun aChangedVariantSaysFromWhatAndCanBeReverted() {
        show(loaded.copy(typed = mapOf(KEPITING_300.id to "7,5")))

        compose.onNodeWithTag(StockTodayTags.row(KEPITING_300.id)).assertTextContains("Diubah dari 12 kg", substring = true)
        compose.onNodeWithTag(StockTodayTags.revert(KEPITING_300.id)).assertTextContains("Kembalikan 12").performClick()
        compose.onNodeWithTag(StockTodayTags.SAVE).assertTextContains(context.getString(R.string.stock_save_changes, 1))

        assertEquals(StockTodayEvent.Revert(KEPITING_300.id), events.last())
    }

    @Test
    fun noChangeSavesAsYesterday() {
        show(loaded)

        compose.onNodeWithTag(StockTodayTags.SAVE).assertTextContains(context.getString(R.string.stock_save_same)).performClick()
        compose.onNodeWithText(context.getString(R.string.stock_help_same)).assertIsDisplayed()

        assertEquals(StockTodayEvent.Save, events.last())
    }

    @Test
    fun aWrongCountShowsUnderItsRowAndTheSaveIsOff() {
        show(loaded.copy(typed = mapOf(KEPITING_300.id to "1,2345")))

        compose.onNodeWithText(context.getString(R.string.stock_error_decimals)).assertIsDisplayed()
        compose.onNodeWithTag(StockTodayTags.SAVE).assertIsNotEnabled()
        compose.onNodeWithText(context.getString(R.string.stock_help_fix)).assertIsDisplayed()
    }

    @Test
    fun aServerRefusalShowsUnderItsRow() {
        show(loaded.copy(typed = mapOf(KEPITING_300.id to "7"), serverErrors = mapOf(KEPITING_300.id to UiText.Raw("Stok terlalu besar."))))

        compose.onNodeWithText("Stok terlalu besar.").assertIsDisplayed()
    }

    @Test
    fun aFailedSaveShowsAboveTheButton() {
        show(loaded.copy(typed = mapOf(KEPITING_300.id to "7,5"), saveError = UiText.Resource(R.string.stock_save_network)))

        compose.onNodeWithText(context.getString(R.string.stock_save_network)).assertIsDisplayed()
        compose.onNodeWithTag(StockTodayTags.SAVE).assertIsEnabled()
    }

    @Test
    fun savingSaysSoAndCannotBeTappedAgain() {
        show(loaded.copy(isSaving = true))

        compose.onNodeWithTag(StockTodayTags.SAVE).assertTextContains(context.getString(R.string.stock_saving)).assertIsNotEnabled()
    }

    @Test
    fun aSavedDayIsAnnounced() {
        show(loaded.copy(notice = UiText.Resource(R.string.stock_confirmed, listOf(4, "07.30"))))

        compose.onNodeWithText(context.getString(R.string.stock_confirmed, 4, "07.30")).assertIsDisplayed()
    }

    @Test
    fun withoutVariantsTheSellerIsSentToProducts() {
        show(StockTodayUiState(isLoading = false, products = emptyList(), now = now))

        compose.onNodeWithText(context.getString(R.string.stock_empty_title)).assertIsDisplayed()
        compose.onNodeWithTag(StockTodayTags.SAVE).assertDoesNotExist()
        compose.onNodeWithTag(StockTodayTags.OPEN_PRODUCTS).performClick()

        assertTrue(openedProducts)
    }

    @Test
    fun aFailedLoadOffersToTryAgain() {
        show(StockTodayUiState(isLoading = false, loadError = UiText.Resource(R.string.error_network), now = now))

        compose.onNodeWithTag(StockTodayTags.RETRY).performClick()

        assertEquals(StockTodayEvent.Retry, events.last())
    }
}
