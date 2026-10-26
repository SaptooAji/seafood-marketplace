package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KAKAP_UTUH
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KEPITING_300
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KEPITING_400
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.RAJUNGAN_SUPER
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.variant
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension
import java.math.BigDecimal
import java.time.Clock
import java.time.Instant
import java.time.ZoneId

/** The "Stok hari ini" screen (#243): counting each variant and confirming the day. */
@OptIn(ExperimentalCoroutinesApi::class)
class StockTodayViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val stock = FakeStockRepository()

    /** 07.30 WIB on Saturday 18 October 2026. */
    private val clock = Clock.fixed(Instant.parse("2026-10-18T00:30:00Z"), ZoneId.of("Asia/Jakarta"))
    private val saved = SavedStateHandle()

    private fun TestScope.loaded(): StockTodayViewModel {
        val vm = StockTodayViewModel(stock, clock, saved)
        advanceUntilIdle()
        return vm
    }

    private fun StockTodayViewModel.type(
        id: Long,
        text: String,
    ) = onEvent(StockTodayEvent.Typed(id, text))

    private val StockTodayViewModel.state get() = uiState.value

    // --- Loading ---------------------------------------------------------

    @Test
    fun `the screen loads every variant with how many were counted today`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            assertFalse(vm.state.isLoading)
            assertEquals(FakeStockRepository.SCREEN, vm.state.products)
            assertEquals("12", vm.state.text(KEPITING_300.id))
            assertEquals("6,5", vm.state.text(KEPITING_400.id))
            assertEquals(2, vm.state.checkedToday)
            assertEquals(4, vm.state.total)
            assertEquals(UiText.Resource(R.string.stock_save_same), vm.state.saveLabel)
        }

    @Test
    fun `a store without active variants shows the empty state`() =
        runTest(main.dispatcher) {
            stock.products = emptyList()

            val vm = loaded()

            assertTrue(vm.state.isEmpty)
            assertFalse(vm.state.canSave)
        }

    @Test
    fun `a failed load says why and tries again`() =
        runTest(main.dispatcher) {
            stock.todayResult = ApiResult.NetworkError
            val vm = loaded()
            assertEquals(UiText.Resource(R.string.error_network), vm.state.loadError)

            stock.todayResult = null
            vm.onEvent(StockTodayEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.state.loadError)
            assertEquals(FakeStockRepository.SCREEN, vm.state.products)
        }

    // --- Changing a count ------------------------------------------------

    @Test
    fun `plus adds half a kilo to a kg variant and one to a piece variant`() =
        runTest(main.dispatcher) {
            stock.products =
                listOf(
                    StockProduct(
                        1,
                        "Uji",
                        listOf(variant(1, "Kg", "2.000"), variant(2, "Ekor", "2.000", VariantUnit.Piece)),
                    ),
                )
            val vm = loaded()

            vm.onEvent(StockTodayEvent.Increase(1))
            vm.onEvent(StockTodayEvent.Increase(2))

            assertEquals("2,5", vm.state.text(1))
            assertEquals("3", vm.state.text(2))
            assertEquals(2, vm.state.changedCount)
        }

    @Test
    fun `minus never goes below zero and is off at zero`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            assertFalse(vm.state.canDecrease(RAJUNGAN_SUPER.id))

            vm.onEvent(StockTodayEvent.Decrease(RAJUNGAN_SUPER.id))

            assertEquals("0", vm.state.text(RAJUNGAN_SUPER.id))
            assertFalse(vm.state.isChanged(RAJUNGAN_SUPER.id))
        }

    @Test
    fun `minus below half a kilo stops at zero`() =
        runTest(main.dispatcher) {
            stock.products = listOf(StockProduct(1, "Uji", listOf(variant(1, "Kg", "0.300"))))
            val vm = loaded()

            vm.onEvent(StockTodayEvent.Decrease(1))

            assertEquals("0", vm.state.text(1))
            assertFalse(vm.state.canDecrease(1))
        }

    @Test
    fun `a typed count marks the variant changed and names the save`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(KEPITING_300.id, "7,5")

            assertTrue(vm.state.isChanged(KEPITING_300.id))
            assertEquals(1, vm.state.changedCount)
            assertEquals(UiText.Resource(R.string.stock_save_changes, listOf(1)), vm.state.saveLabel)
            assertEquals(UiText.Resource(R.string.stock_help_changes), vm.state.saveHelp)
        }

    @Test
    fun `typing the old value back is no change`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(KEPITING_400.id, "7")
            vm.type(KEPITING_400.id, "6.50")

            assertFalse(vm.state.isChanged(KEPITING_400.id))
            assertEquals(UiText.Resource(R.string.stock_save_same), vm.state.saveLabel)
        }

    @Test
    fun `revert puts the loaded count back`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockTodayEvent.Increase(KEPITING_300.id))

            vm.onEvent(StockTodayEvent.Revert(KEPITING_300.id))

            assertEquals("12", vm.state.text(KEPITING_300.id))
            assertEquals(0, vm.state.changedCount)
        }

    @Test
    fun `a large count is shown without a thousands dot so plus reads it right`() =
        runTest(main.dispatcher) {
            stock.products = listOf(StockProduct(1, "Uji", listOf(variant(1, "Kg", "1500.000"))))
            val vm = loaded()
            assertEquals("1500", vm.state.text(1))

            vm.onEvent(StockTodayEvent.Increase(1))

            assertEquals("1500,5", vm.state.text(1))
        }

    @Test
    fun `more than three decimals is refused on its row and blocks the save`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(KEPITING_300.id, "1,2345")

            assertEquals(UiText.Resource(R.string.stock_error_decimals), vm.state.error(KEPITING_300.id))
            assertNull(vm.state.error(KEPITING_400.id))
            assertFalse(vm.state.canSave)
            assertEquals(UiText.Resource(R.string.stock_help_fix), vm.state.saveHelp)
        }

    @Test
    fun `a negative count is refused`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(KEPITING_300.id, "-1")

            assertEquals(UiText.Resource(R.string.stock_error_negative), vm.state.error(KEPITING_300.id))
            assertFalse(vm.state.canSave)
        }

    @Test
    fun `an empty or non-numeric count is refused`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(KEPITING_300.id, "")
            vm.type(KEPITING_400.id, "dua")

            assertEquals(UiText.Resource(R.string.stock_error_number), vm.state.error(KEPITING_300.id))
            assertEquals(UiText.Resource(R.string.stock_error_number), vm.state.error(KEPITING_400.id))
        }

    @Test
    fun `a count for a variant the screen did not load is ignored`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.type(999, "5")
            vm.onEvent(StockTodayEvent.Increase(999))
            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(listOf(emptyList<StockCount>()), stock.confirmed)
        }

    // --- Saving ----------------------------------------------------------

    @Test
    fun `no change confirms everything as it is`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(listOf(emptyList<StockCount>()), stock.confirmed)
        }

    @Test
    fun `only the changed variants are sent, in screen order`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockTodayEvent.Increase(KAKAP_UTUH.id))
            vm.type(KEPITING_300.id, "7,5")

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(
                listOf(listOf(StockCount(KEPITING_300.id, BigDecimal("7.5")), StockCount(KAKAP_UTUH.id, BigDecimal("3")))),
                stock.confirmed,
            )
        }

    @Test
    fun `a saved day shows the server's screen and says so`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.type(KEPITING_300.id, "7,5")
            val after = listOf(StockProduct(7, "Kepiting Bakau Super", listOf(KEPITING_300.copy(stock = BigDecimal("7.500")))))
            stock.products = after

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(after, vm.state.products)
            assertEquals("7,5", vm.state.text(KEPITING_300.id))
            assertEquals(0, vm.state.changedCount)
            assertFalse(vm.state.isSaving)
            assertEquals(UiText.Resource(R.string.stock_confirmed, listOf(1, "07.30")), vm.state.notice)

            vm.onEvent(StockTodayEvent.NoticeShown)
            assertNull(vm.state.notice)
        }

    @Test
    fun `a second tap while saving sends nothing more`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(StockTodayEvent.Save)
            assertTrue(vm.state.isSaving)
            assertEquals(UiText.Resource(R.string.stock_saving), vm.state.saveLabel)
            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(1, stock.confirmed.size)
        }

    @Test
    fun `a refused count shows on the row it was sent for`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.type(KEPITING_300.id, "7")
            vm.type(KEPITING_400.id, "5")
            stock.confirmResult =
                ApiResult.Invalid(mapOf("variants.1.stock" to listOf("Stok terlalu besar.")), "Stok terlalu besar.")

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Stok terlalu besar."), vm.state.error(KEPITING_400.id))
            assertNull(vm.state.error(KEPITING_300.id))
            assertEquals("5", vm.state.text(KEPITING_400.id))

            vm.type(KEPITING_400.id, "4")
            assertNull(vm.state.error(KEPITING_400.id))
        }

    @Test
    fun `a refusal not about one row shows above the button`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            stock.confirmResult = ApiResult.Invalid(mapOf("variants" to listOf("Daftar ukuran tidak valid.")), "Daftar ukuran tidak valid.")

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Daftar ukuran tidak valid."), vm.state.saveError)
        }

    @Test
    fun `without a connection the changes stay to save again`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.type(KEPITING_300.id, "7,5")
            stock.confirmResult = ApiResult.NetworkError

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.stock_save_network), vm.state.saveError)
            assertEquals("7,5", vm.state.text(KEPITING_300.id))
            assertTrue(vm.state.canSave)

            stock.confirmResult = null
            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()
            assertNull(vm.state.saveError)
            assertEquals(2, stock.confirmed.size)
        }

    @Test
    fun `a server failure keeps the changes too`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.type(KEPITING_300.id, "7,5")
            stock.confirmResult = ApiResult.ServerError

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.stock_save_server), vm.state.saveError)
            assertTrue(vm.state.isChanged(KEPITING_300.id))
        }

    @Test
    fun `a suspended store hears the server's reason`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            stock.confirmResult = ApiResult.Forbidden("Toko Anda sedang ditangguhkan.")

            vm.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Toko Anda sedang ditangguhkan."), vm.state.saveError)
        }

    // --- Rotation --------------------------------------------------------

    @Test
    fun `unsaved changes survive the screen being recreated`() =
        runTest(main.dispatcher) {
            val first = loaded()
            first.type(KEPITING_300.id, "7,5")
            first.type(KEPITING_400.id, "1,2345")

            val second = loaded()

            assertEquals("7,5", second.state.text(KEPITING_300.id))
            assertEquals("1,2345", second.state.text(KEPITING_400.id))
            assertEquals(2, second.state.changedCount)
        }

    @Test
    fun `a saved day leaves nothing to restore`() =
        runTest(main.dispatcher) {
            val first = loaded()
            first.type(KEPITING_300.id, "7,5")
            first.onEvent(StockTodayEvent.Save)
            advanceUntilIdle()

            val second = loaded()

            assertEquals(0, second.state.changedCount)
        }
}
