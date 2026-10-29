package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.KEPITING_300
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.movement
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
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
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.MethodSource
import java.math.BigDecimal
import java.time.Clock
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneId

/** The stock book (#244): its pages, its filters, and recording a change. */
@OptIn(ExperimentalCoroutinesApi::class)
class StockBookViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val stock = FakeStockRepository()

    /** 07.30 WIB on Sunday 18 October 2026. */
    private val clock = Clock.fixed(Instant.parse("2026-10-18T00:30:00Z"), ZoneId.of("Asia/Jakarta"))
    private val saved = SavedStateHandle()

    private fun TestScope.loaded(): StockBookViewModel {
        val vm = StockBookViewModel(stock, clock, saved)
        advanceUntilIdle()
        return vm
    }

    private val StockBookViewModel.state get() = uiState.value

    private fun StockBookViewModel.type(
        field: RecordField,
        text: String,
    ) = onEvent(StockBookEvent.RecordTextChanged(field, text))

    /** The sheet open on [type] for Kepiting 300–400 g. */
    private fun TestScope.recording(type: StockMovementType): StockBookViewModel {
        val vm = loaded()
        vm.onEvent(StockBookEvent.OpenRecord)
        vm.onEvent(StockBookEvent.RecordTypePicked(type))
        vm.onEvent(StockBookEvent.RecordVariantPicked(KEPITING_300.id))
        return vm
    }

    // --- The history -----------------------------------------------------

    @Test
    fun `the first page loads unfiltered with the variants to pick from`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            assertFalse(vm.state.isLoading)
            assertEquals(FakeStockRepository.BOOK, vm.state.movements)
            assertEquals(listOf(FakeStockRepository.MovementCall(StockFilter(), 1)), stock.movementCalls)
            assertEquals(FakeStockRepository.SCREEN, vm.state.products)
            assertEquals(LocalDate.of(2026, 10, 18), vm.state.today)
        }

    @Test
    fun `scrolling on loads the next page of 30 and then stops`() =
        runTest(main.dispatcher) {
            stock.movements = (1L..31L).map { movement(it) }
            val vm = loaded()
            assertEquals(30, vm.state.movements.size)

            vm.onEvent(StockBookEvent.LoadMore)
            advanceUntilIdle()
            vm.onEvent(StockBookEvent.LoadMore)
            advanceUntilIdle()

            assertEquals(31, vm.state.movements.size)
            assertTrue(vm.state.endReached)
            assertEquals(listOf(1, 2), stock.movementCalls.map { it.page })
        }

    @Test
    fun `a failed first page says why and tries again`() =
        runTest(main.dispatcher) {
            stock.movementsResult = ApiResult.NetworkError
            val vm = loaded()
            assertEquals(UiText.Resource(R.string.error_network), vm.state.error)

            stock.movementsResult = null
            vm.onEvent(StockBookEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.state.error)
            assertEquals(FakeStockRepository.BOOK, vm.state.movements)
        }

    // --- Filters ---------------------------------------------------------

    @Test
    fun `a filter reloads from page 1 with its query`() =
        runTest(main.dispatcher) {
            stock.movements = (1L..31L).map { movement(it) }
            val vm = loaded()
            vm.onEvent(StockBookEvent.LoadMore)
            advanceUntilIdle()

            vm.onEvent(StockBookEvent.OpenFilter)
            vm.onEvent(StockBookEvent.FilterTypePicked(StockMovementType.SaleOffline))
            vm.onEvent(StockBookEvent.FilterDatesChanged(LocalDate.of(2026, 10, 1), LocalDate.of(2026, 10, 7)))
            vm.onEvent(StockBookEvent.ApplyFilter)
            advanceUntilIdle()

            val wanted = StockFilter(type = StockMovementType.SaleOffline, from = LocalDate.of(2026, 10, 1), to = LocalDate.of(2026, 10, 7))
            assertEquals(FakeStockRepository.MovementCall(wanted, 1), stock.movementCalls.last())
            assertEquals(wanted, vm.state.filter)
            assertNull(vm.state.filterSheet)
            assertEquals(30, vm.state.movements.size)
        }

    @Test
    fun `an end date before the start is refused and nothing is asked`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockBookEvent.OpenFilter)

            vm.onEvent(StockBookEvent.FilterDatesChanged(LocalDate.of(2026, 10, 7), LocalDate.of(2026, 10, 1)))
            vm.onEvent(StockBookEvent.ApplyFilter)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.stock_book_error_dates), vm.state.filterSheet?.error)
            assertEquals(1, stock.movementCalls.size)
        }

    @Test
    fun `a variant filter is sent by its id`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockBookEvent.OpenFilter)

            vm.onEvent(StockBookEvent.FilterVariantPicked(KEPITING_300.id))
            vm.onEvent(StockBookEvent.ApplyFilter)
            advanceUntilIdle()

            assertEquals(StockFilter(variantId = KEPITING_300.id), stock.movementCalls.last().filter)
        }

    @Test
    fun `clearing the filter shows everything again`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockBookEvent.OpenFilter)
            vm.onEvent(StockBookEvent.FilterTypePicked(StockMovementType.Shrinkage))
            vm.onEvent(StockBookEvent.ApplyFilter)
            advanceUntilIdle()

            vm.onEvent(StockBookEvent.ClearFilter)
            advanceUntilIdle()

            assertEquals(StockFilter(), vm.state.filter)
            assertEquals(StockFilter(), stock.movementCalls.last().filter)
        }

    @Test
    fun `the filter offers every type and the record sheet only those a seller may write`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            assertEquals(StockMovementType.entries, vm.state.filterTypes)
            assertEquals(
                listOf(StockMovementType.StockIn, StockMovementType.SaleOffline, StockMovementType.Shrinkage, StockMovementType.Adjustment),
                vm.state.recordTypes,
            )
        }

    @Test
    fun `the filter survives the screen being recreated`() =
        runTest(main.dispatcher) {
            val first = loaded()
            first.onEvent(StockBookEvent.OpenFilter)
            first.onEvent(StockBookEvent.FilterTypePicked(StockMovementType.Shrinkage))
            first.onEvent(StockBookEvent.ApplyFilter)
            advanceUntilIdle()

            val second = loaded()

            assertEquals(StockFilter(type = StockMovementType.Shrinkage), second.state.filter)
            assertEquals(StockFilter(type = StockMovementType.Shrinkage), stock.movementCalls.last().filter)
        }

    // --- Recording -------------------------------------------------------

    @Test
    fun `stock in from a supplier is recorded as typed`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.StockIn)
            vm.type(RecordField.Qty, "5")
            vm.type(RecordField.Supplier, "Pak Darto")
            vm.type(RecordField.UnitCost, "40.000")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(
                listOf(NewStockMovement(KEPITING_300.id, StockMovementType.StockIn, BigDecimal("5"), "Pak Darto", 40_000, null)),
                stock.recorded,
            )
        }

    @Test
    fun `shrinkage takes a decimal with a comma`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.type(RecordField.Qty, "0,5")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(BigDecimal("0.5"), stock.recorded.single().qty)
            assertEquals(StockMovementType.Shrinkage, stock.recorded.single().type)
        }

    @Test
    fun `an adjustment may go down`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Adjustment)
            vm.type(RecordField.Qty, "-2")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(BigDecimal("-2"), stock.recorded.single().qty)
        }

    @Test
    fun `a supplier typed before switching away from stock in is not sent`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.StockIn)
            vm.type(RecordField.Supplier, "Pak Darto")
            vm.type(RecordField.UnitCost, "40000")
            vm.onEvent(StockBookEvent.RecordTypePicked(StockMovementType.SaleOffline))
            vm.type(RecordField.Qty, "2")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertNull(stock.recorded.single().supplierName)
            assertNull(stock.recorded.single().unitCost)
        }

    @Test
    fun `the sheet tells the stock it will leave`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.StockIn)

            vm.type(RecordField.Qty, "5")
            assertEquals(UiText.Resource(R.string.stock_record_preview, listOf("17", "kg", "12", "kg")), vm.state.record?.preview)

            vm.onEvent(StockBookEvent.RecordTypePicked(StockMovementType.Shrinkage))
            vm.type(RecordField.Qty, "0,5")
            assertEquals(UiText.Resource(R.string.stock_record_preview, listOf("11,5", "kg", "12", "kg")), vm.state.record?.preview)
        }

    @ParameterizedTest(name = "{0}")
    @MethodSource("refusedInput")
    fun `wrong input is refused on its field and nothing is sent`(
        case: String,
        type: StockMovementType,
        field: RecordField,
        text: String,
        error: UiText,
    ) = runTest(main.dispatcher) {
        val vm = recording(type)
        if (field != RecordField.Qty) vm.type(RecordField.Qty, "1")
        vm.type(field, text)

        vm.onEvent(StockBookEvent.SaveRecord)
        advanceUntilIdle()

        assertEquals(error, vm.state.record?.errors?.get(field), case)
        assertTrue(stock.recorded.isEmpty())
    }

    @Test
    fun `no variant picked is refused`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(StockBookEvent.OpenRecord)
            vm.type(RecordField.Qty, "1")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.stock_record_error_variant), vm.state.record?.errors?.get(RecordField.Variant))
            assertTrue(stock.recorded.isEmpty())
        }

    @Test
    fun `typing again clears that field's error`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.onEvent(StockBookEvent.SaveRecord)
            assertTrue(vm.state.record?.errors?.containsKey(RecordField.Qty) == true)

            vm.type(RecordField.Qty, "1")

            assertNull(vm.state.record?.errors?.get(RecordField.Qty))
        }

    @Test
    fun `too little stock shows the server's message on the quantity`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.type(RecordField.Qty, "15")
            stock.recordResult = ApiResult.Invalid(mapOf("qty" to listOf("Stok tidak cukup: sekarang 12 kg.")), "Stok tidak cukup.")

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Stok tidak cukup: sekarang 12 kg."), vm.state.record?.errors?.get(RecordField.Qty))
            assertFalse(vm.state.record?.isSaving ?: true)
            assertEquals("15", vm.state.record?.qty)
        }

    @Test
    fun `a failed save keeps the sheet to try again`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.type(RecordField.Qty, "1")
            stock.recordResult = ApiResult.NetworkError

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.stock_record_save_network), vm.state.record?.saveError)
            assertEquals("1", vm.state.record?.qty)
        }

    @Test
    fun `a saved change closes the sheet and reloads the book and the stock`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.type(RecordField.Qty, "1")
            val todayCalls = stock.todayCalls

            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertNull(vm.state.record)
            assertEquals(UiText.Resource(R.string.stock_record_saved), vm.state.notice)
            assertEquals(1, vm.state.recorded)
            assertEquals(2, stock.movementCalls.size)
            assertEquals(1, stock.movementCalls.last().page)
            assertEquals(todayCalls + 1, stock.todayCalls)
        }

    @Test
    fun `a second tap while saving sends nothing more`() =
        runTest(main.dispatcher) {
            val vm = recording(StockMovementType.Shrinkage)
            vm.type(RecordField.Qty, "1")

            vm.onEvent(StockBookEvent.SaveRecord)
            vm.onEvent(StockBookEvent.SaveRecord)
            advanceUntilIdle()

            assertEquals(1, stock.recorded.size)
        }

    companion object {
        private fun refused(id: Int) = UiText.Resource(id)

        @JvmStatic
        fun refusedInput(): List<Arguments> =
            listOf(
                Arguments.of("no quantity", StockMovementType.StockIn, RecordField.Qty, "", refused(R.string.stock_record_error_qty)),
                Arguments.of("zero", StockMovementType.StockIn, RecordField.Qty, "0", refused(R.string.stock_record_error_zero)),
                Arguments.of("four decimals", StockMovementType.Shrinkage, RecordField.Qty, "1,2345", refused(R.string.stock_error_decimals)),
                Arguments.of("minus on stock in", StockMovementType.StockIn, RecordField.Qty, "-2", refused(R.string.stock_record_error_minus)),
                Arguments.of("not a number", StockMovementType.SaleOffline, RecordField.Qty, "dua", refused(R.string.stock_error_number)),
                Arguments.of(
                    "a supplier of 256 characters",
                    StockMovementType.StockIn,
                    RecordField.Supplier,
                    "a".repeat(256),
                    UiText.Resource(R.string.stock_record_error_too_long, listOf(255)),
                ),
                Arguments.of("a negative cost", StockMovementType.StockIn, RecordField.UnitCost, "-1", refused(R.string.stock_record_error_cost)),
                Arguments.of(
                    "a cost above 100 million",
                    StockMovementType.StockIn,
                    RecordField.UnitCost,
                    "100.000.001",
                    refused(R.string.stock_record_error_cost),
                ),
                Arguments.of(
                    "a note of 1001 characters",
                    StockMovementType.Shrinkage,
                    RecordField.Note,
                    "a".repeat(1001),
                    UiText.Resource(R.string.stock_record_error_too_long, listOf(1000)),
                ),
            )
    }
}
