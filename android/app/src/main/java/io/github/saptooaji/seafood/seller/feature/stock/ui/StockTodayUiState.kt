package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant
import java.math.BigDecimal
import java.time.ZonedDateTime

/**
 * "Stok hari ini" (#243). Only the counts the seller touched are kept, as
 * typed, in [typed]; every other variant shows the server's stock.
 *
 * @param serverErrors a refused count, by variant id, until it is typed again
 * @param saveError a failed save, shown above the button; the counts stay
 * @param notice "Stok dikonfirmasi ...", shown once after a save
 * @param now when the screen was loaded, in the phone's time zone
 */
data class StockTodayUiState(
    val isLoading: Boolean = true,
    val loadError: UiText? = null,
    val products: List<StockProduct> = emptyList(),
    val typed: Map<Long, String> = emptyMap(),
    val serverErrors: Map<Long, UiText> = emptyMap(),
    val isSaving: Boolean = false,
    val saveError: UiText? = null,
    val notice: UiText? = null,
    val now: ZonedDateTime = ZonedDateTime.now(),
) {
    val variants: List<StockVariant> get() = products.flatMap { it.variants }

    val total: Int get() = variants.size

    /** Counted on today's date; the ring at the top shows these out of [total]. */
    val checkedToday: Int get() = variants.count { isCheckedToday(it.confirmedAt, now) }

    val isEmpty: Boolean get() = !isLoading && loadError == null && products.isEmpty()

    /** The variants whose count differs from the server's, in screen order. */
    val changed: List<StockVariant> get() = variants.filter { isChanged(it.id) }

    val changedCount: Int get() = changed.size

    val hasErrors: Boolean get() = variants.any { error(it.id) != null }

    val canSave: Boolean get() = !isLoading && !isSaving && products.isNotEmpty() && !hasErrors

    val saveLabel: UiText
        get() =
            when {
                isSaving -> UiText.Resource(R.string.stock_saving)
                changedCount > 0 -> UiText.Resource(R.string.stock_save_changes, listOf(changedCount))
                else -> UiText.Resource(R.string.stock_save_same)
            }

    val saveHelp: UiText
        get() =
            UiText.Resource(
                when {
                    hasErrors -> R.string.stock_help_fix
                    changedCount > 0 -> R.string.stock_help_changes
                    else -> R.string.stock_help_same
                },
            )

    fun variant(id: Long): StockVariant? = variants.firstOrNull { it.id == id }

    /** The count in its field: as typed, else the server's without a thousands dot (`1500`, `6,5`). */
    fun text(id: Long): String = typed[id] ?: variant(id)?.stock?.let(::fieldText).orEmpty()

    /** The count as a number, or null while what was typed is not one. */
    fun counted(id: Long): BigDecimal? = parseCount(text(id))

    /** Touched and different from the server's; a count that is not a number yet counts as changed. */
    fun isChanged(id: Long): Boolean {
        val loaded = variant(id)?.stock
        val typedText = typed[id]
        return loaded != null && typedText != null && parseCount(typedText)?.compareTo(loaded) != 0
    }

    fun error(id: Long): UiText? = serverErrors[id] ?: typed[id]?.let(::countError)

    fun canDecrease(id: Long): Boolean = (counted(id) ?: BigDecimal.ZERO).signum() > 0
}
