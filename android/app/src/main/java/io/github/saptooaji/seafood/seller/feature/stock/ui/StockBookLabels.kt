package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.annotation.StringRes
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import java.math.BigDecimal
import java.time.LocalDate
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

private val INDONESIAN = Locale.forLanguageTag("id-ID")
private val TIME = DateTimeFormatter.ofPattern("HH.mm", INDONESIAN)
private val DAY = DateTimeFormatter.ofPattern("EEEE, d MMMM", INDONESIAN)
private val SHORT_DAY = DateTimeFormatter.ofPattern("d MMM", INDONESIAN)
private const val MINUS = "−"

/** `+5 kg`, `−2,5 kg`: the movement's change in its unit. */
fun signedQuantity(
    qty: BigDecimal,
    unitLabel: String,
): String {
    val sign =
        when (qty.signum()) {
            1 -> "+"
            -1 -> MINUS
            else -> ""
        }
    return sign + IndonesianNumbers.decimal(qty.abs()) + " " + unitLabel.lowercase()
}

/** "jadi 12,5 kg": the stock right after the movement. */
fun stockAfter(movement: StockMovement): UiText =
    UiText.Resource(R.string.stock_book_after, listOf(IndonesianNumbers.decimal(movement.stockAfter), movement.unitLabel.lowercase()))

/**
 * The supplier, the cost per unit, the note, who recorded it, and when, with
 * what is missing left out: "Pak Darto, Rp140.000/kg, Budi, 05.10".
 */
fun movementDetails(
    movement: StockMovement,
    zone: ZoneId,
): String =
    listOfNotNull(
        movement.supplierName?.takeIf { it.isNotBlank() },
        movement.unitCost?.let { IndonesianNumbers.rupiah(it) + "/" + movement.unitLabel.lowercase() },
        movement.note?.takeIf { it.isNotBlank() },
        movement.createdBy,
        movement.createdAt?.let { TIME.format(it.atZone(zone)) },
    ).joinToString(", ")

/** The day a movement is grouped under, in the phone's time zone. */
fun dayOf(
    movement: StockMovement,
    zone: ZoneId,
): LocalDate? = movement.createdAt?.atZone(zone)?.toLocalDate()

/** "Hari ini", "Kemarin", or the date: "Kamis, 15 Oktober". */
fun dayHeading(
    day: LocalDate,
    today: LocalDate,
): UiText =
    when (day) {
        today -> UiText.Resource(R.string.stock_book_today)
        today.minusDays(1) -> UiText.Resource(R.string.stock_book_yesterday)
        else -> UiText.Raw(DAY.format(day))
    }

/** The date chip: "Semua tanggal", "1–7 Okt", "28 Sep–3 Okt", "Sejak 1 Okt", or "Sampai 7 Okt". */
fun dateRangeLabel(
    from: LocalDate?,
    to: LocalDate?,
): UiText =
    when {
        from == null && to == null -> UiText.Resource(R.string.stock_book_all_dates)
        to == null -> UiText.Resource(R.string.stock_book_since, listOf(SHORT_DAY.format(from)))
        from == null -> UiText.Resource(R.string.stock_book_until, listOf(SHORT_DAY.format(to)))
        from.year == to.year && from.month == to.month -> UiText.Raw("${from.dayOfMonth}–${SHORT_DAY.format(to)}")
        else -> UiText.Raw("${SHORT_DAY.format(from)}–${SHORT_DAY.format(to)}")
    }

/** A short date for the filter's fields: "7 Okt 2026". */
fun fieldDate(day: LocalDate): String = DateTimeFormatter.ofPattern("d MMM yyyy", INDONESIAN).format(day)

/** The app's own name for a type, for chips and tiles. */
@StringRes
fun typeName(type: StockMovementType): Int =
    when (type) {
        StockMovementType.StockIn -> R.string.stock_type_stock_in
        StockMovementType.SaleApp -> R.string.stock_type_sale_app
        StockMovementType.SaleOffline -> R.string.stock_type_sale_offline
        StockMovementType.Shrinkage -> R.string.stock_type_shrinkage
        StockMovementType.Adjustment -> R.string.stock_type_adjustment
        StockMovementType.CancelReturn -> R.string.stock_type_cancel_return
    }

/** The hint under a type's tile in the record sheet: "Dari nelayan". */
@StringRes
fun typeHint(type: StockMovementType): Int =
    when (type) {
        StockMovementType.StockIn -> R.string.stock_type_stock_in_hint
        StockMovementType.SaleOffline -> R.string.stock_type_sale_offline_hint
        StockMovementType.Shrinkage -> R.string.stock_type_shrinkage_hint
        else -> R.string.stock_type_adjustment_hint
    }
