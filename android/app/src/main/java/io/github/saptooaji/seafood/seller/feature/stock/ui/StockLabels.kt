package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import java.time.Instant
import java.time.ZonedDateTime
import java.time.format.DateTimeFormatter
import java.time.temporal.ChronoUnit
import java.util.Locale

private val INDONESIAN = Locale.forLanguageTag("id-ID")
private val TIME: DateTimeFormatter = DateTimeFormatter.ofPattern("HH.mm", INDONESIAN)
private val DAY: DateTimeFormatter = DateTimeFormatter.ofPattern("EEEE, d MMMM", INDONESIAN)

/** `Sabtu, 18 Oktober`: the day above the title. */
fun headerDate(now: ZonedDateTime): String = DAY.format(now)

/** `05.52`: a time of day the Indonesian way. */
fun clockTime(now: ZonedDateTime): String = TIME.format(now)

/**
 * When a variant was last counted, by the date in the phone's time zone
 * ([now]'s zone): "Dicek 05.40", "Dicek kemarin 06.15", "Belum dicek 3 hari",
 * or "Belum pernah dicek". The server sends UTC.
 */
fun checkedNote(
    confirmedAt: Instant?,
    now: ZonedDateTime,
): UiText {
    if (confirmedAt == null) return UiText.Resource(R.string.stock_never_checked)
    val at = confirmedAt.atZone(now.zone)
    val days = ChronoUnit.DAYS.between(at.toLocalDate(), now.toLocalDate())
    return when {
        days <= 0 -> UiText.Resource(R.string.stock_checked_today, listOf(TIME.format(at)))
        days == 1L -> UiText.Resource(R.string.stock_checked_yesterday, listOf(TIME.format(at)))
        else -> UiText.Resource(R.string.stock_unchecked_days, listOf(days.toInt()))
    }
}

/** Counted on the phone's date of [now]; the summary at the top counts these. */
fun isCheckedToday(
    confirmedAt: Instant?,
    now: ZonedDateTime,
): Boolean = confirmedAt != null && confirmedAt.atZone(now.zone).toLocalDate() == now.toLocalDate()
