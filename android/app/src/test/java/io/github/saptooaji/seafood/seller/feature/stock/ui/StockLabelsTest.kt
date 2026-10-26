package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import java.time.Instant
import java.time.ZoneId
import java.time.ZonedDateTime

/** When a variant was last counted, in the phone's own time (#243). */
class StockLabelsTest {
    private val jakarta = ZoneId.of("Asia/Jakarta")

    /** 07.30 WIB on Saturday 18 October 2026. */
    private val now = ZonedDateTime.of(2026, 10, 18, 7, 30, 0, 0, jakarta)

    @Test
    fun `a UTC time late on the 17th is this morning in WIB`() {
        assertEquals(
            UiText.Resource(R.string.stock_checked_today, listOf("06.40")),
            checkedNote(Instant.parse("2026-10-17T23:40:00Z"), now),
        )
    }

    @Test
    fun `yesterday says so with its time`() {
        assertEquals(
            UiText.Resource(R.string.stock_checked_yesterday, listOf("06.15")),
            checkedNote(Instant.parse("2026-10-16T23:15:00Z"), now),
        )
    }

    @Test
    fun `older counts say how many days ago`() {
        assertEquals(
            UiText.Resource(R.string.stock_unchecked_days, listOf(3)),
            checkedNote(Instant.parse("2026-10-14T23:20:00Z"), now),
        )
    }

    @Test
    fun `a variant never counted says so`() {
        assertEquals(UiText.Resource(R.string.stock_never_checked), checkedNote(null, now))
    }

    @Test
    fun `just after midnight yesterday evening is yesterday`() {
        val earlyMorning = ZonedDateTime.of(2026, 10, 18, 0, 5, 0, 0, jakarta)

        assertEquals(
            UiText.Resource(R.string.stock_checked_yesterday, listOf("23.50")),
            checkedNote(Instant.parse("2026-10-17T16:50:00Z"), earlyMorning),
        )
    }

    @Test
    fun `the header names the day in Indonesian`() {
        assertEquals("Sabtu, 18 Oktober", headerDate(now))
    }

    @Test
    fun `a count is checked today only when it falls on the phone's date`() {
        assertEquals(true, isCheckedToday(Instant.parse("2026-10-17T23:40:00Z"), now))
        assertEquals(false, isCheckedToday(Instant.parse("2026-10-17T16:50:00Z"), now))
        assertEquals(false, isCheckedToday(null, now))
    }
}
