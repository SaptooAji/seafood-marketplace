package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.FakeStockRepository.Companion.movement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import java.math.BigDecimal
import java.time.LocalDate
import java.time.ZoneId

/** How one stock book line reads (#244). */
class StockBookLabelsTest {
    private val jakarta = ZoneId.of("Asia/Jakarta")
    private val today = LocalDate.of(2026, 10, 18)

    @Test
    fun `the quantity carries its sign and unit`() {
        assertEquals("+5 kg", signedQuantity(BigDecimal("5.000"), "Kg"))
        assertEquals("−2 kg", signedQuantity(BigDecimal("-2.000"), "Kg"))
        assertEquals("−0,5 kg", signedQuantity(BigDecimal("-0.500"), "Kg"))
        assertEquals("+1.250,75 pcs", signedQuantity(BigDecimal("1250.750"), "Pcs"))
    }

    @Test
    fun `the stock after reads as it became`() {
        assertEquals(UiText.Resource(R.string.stock_book_after, listOf("12,5", "kg")), stockAfter(movement(1)))
    }

    @Test
    fun `details join what there is, ending with who and when`() {
        val stockIn = movement(1).copy(supplierName = "Pak Darto", unitCost = 140_000, note = "Setoran pagi")

        assertEquals("Pak Darto, Rp140.000/kg, Setoran pagi, Budi, 05.10", movementDetails(stockIn, jakarta))
    }

    @Test
    fun `a line without a supplier leaves no empty part`() {
        val sale = movement(2, StockMovementType.SaleOffline, qty = "-2.000")

        assertEquals("Budi, 05.10", movementDetails(sale, jakarta))
    }

    @Test
    fun `a system line has no recorder`() {
        assertEquals("05.10", movementDetails(movement(3).copy(createdBy = null), jakarta))
    }

    @Test
    fun `days are grouped as today, yesterday, or their date`() {
        assertEquals(UiText.Resource(R.string.stock_book_today), dayHeading(today, today))
        assertEquals(UiText.Resource(R.string.stock_book_yesterday), dayHeading(today.minusDays(1), today))
        assertEquals(UiText.Raw("Kamis, 15 Oktober"), dayHeading(today.minusDays(3), today))
    }

    @Test
    fun `the date chip names the range`() {
        assertEquals(UiText.Resource(R.string.stock_book_all_dates), dateRangeLabel(null, null))
        assertEquals(UiText.Raw("1–7 Okt"), dateRangeLabel(LocalDate.of(2026, 10, 1), LocalDate.of(2026, 10, 7)))
        assertEquals(UiText.Raw("28 Sep–3 Okt"), dateRangeLabel(LocalDate.of(2026, 9, 28), LocalDate.of(2026, 10, 3)))
        assertEquals(UiText.Resource(R.string.stock_book_since, listOf("1 Okt")), dateRangeLabel(LocalDate.of(2026, 10, 1), null))
        assertEquals(UiText.Resource(R.string.stock_book_until, listOf("7 Okt")), dateRangeLabel(null, LocalDate.of(2026, 10, 7)))
    }
}
