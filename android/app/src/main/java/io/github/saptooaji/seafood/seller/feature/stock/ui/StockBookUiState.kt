package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant
import java.time.LocalDate
import java.time.ZoneId

/**
 * The stock book (#244): pages of movements, newest first, with the filter
 * in use, and the two sheets that may be open over it.
 *
 * @param products the active variants of Stok hari ini, to filter by and record for
 * @param recorded counts the changes recorded here, so Stok hari ini reloads
 * @param today and [zone] place each movement on a day of the phone's calendar
 */
data class StockBookUiState(
    val isLoading: Boolean = true,
    val error: UiText? = null,
    val movements: List<StockMovement> = emptyList(),
    val isLoadingMore: Boolean = false,
    val loadMoreError: UiText? = null,
    val endReached: Boolean = false,
    val filter: StockFilter = StockFilter(),
    val filterSheet: FilterDraft? = null,
    val record: RecordSheet? = null,
    val products: List<StockProduct> = emptyList(),
    val notice: UiText? = null,
    val recorded: Int = 0,
    val today: LocalDate = LocalDate.now(),
    val zone: ZoneId = ZoneId.systemDefault(),
) {
    val filterTypes: List<StockMovementType> get() = StockMovementType.entries

    val recordTypes: List<StockMovementType> get() = StockMovementType.recordable

    val canLoadMore: Boolean get() = !isLoading && !isLoadingMore && !endReached && error == null && loadMoreError == null

    fun variant(id: Long?): StockVariant? = products.flatMap { it.variants }.firstOrNull { it.id == id }

    /** "Kepiting Bakau Super, 300–400 g per ekor", or null for a variant not on Stok hari ini. */
    fun variantTitle(id: Long?): String? {
        val product = products.firstOrNull { product -> product.variants.any { it.id == id } } ?: return null
        return product.name + ", " + product.variants.first { it.id == id }.name
    }
}

/** The filter as it is being chosen in its sheet, before it is applied. */
data class FilterDraft(
    val type: StockMovementType? = null,
    val variantId: Long? = null,
    val from: LocalDate? = null,
    val to: LocalDate? = null,
) {
    /** An end before the start, refused before anything is asked (`after_or_equal:from`). */
    val error: UiText? get() = if (from != null && to != null && to < from) UiText.Resource(R.string.stock_book_error_dates) else null

    fun toFilter() = StockFilter(variantId = variantId, type = type, from = from, to = to)

    companion object {
        fun of(filter: StockFilter) = FilterDraft(filter.type, filter.variantId, filter.from, filter.to)
    }
}

enum class RecordField { Variant, Qty, Supplier, UnitCost, Note }

/**
 * The record sheet (board VD-Catat-Stok): what is typed, as typed.
 *
 * @param variant the picked variant as Stok hari ini shows it, for the stock it will leave
 * @param pickingVariant the variant list is open over the sheet
 */
data class RecordSheet(
    val type: StockMovementType = StockMovementType.StockIn,
    val variantId: Long? = null,
    val qty: String = "",
    val supplier: String = "",
    val unitCost: String = "",
    val note: String = "",
    val errors: Map<RecordField, UiText> = emptyMap(),
    val isSaving: Boolean = false,
    val saveError: UiText? = null,
    val pickingVariant: Boolean = false,
    val variant: StockVariant? = null,
) {
    /** "Stok jadi 17 kg (sekarang 12 kg)" while the quantity is a number. */
    val preview: UiText?
        get() {
            val current = variant ?: return null
            val change = recordQuantity(qty) ?: return null
            val after = current.stock + if (type.direction() < 0) change.negate() else change
            val unit = current.unitLabel.lowercase()
            return UiText.Resource(
                R.string.stock_record_preview,
                listOf(IndonesianNumbers.decimal(after), unit, IndonesianNumbers.decimal(current.stock), unit),
            )
        }

    fun text(field: RecordField): String =
        when (field) {
            RecordField.Variant -> ""
            RecordField.Qty -> qty
            RecordField.Supplier -> supplier
            RecordField.UnitCost -> unitCost
            RecordField.Note -> note
        }

    fun withText(
        field: RecordField,
        text: String,
    ): RecordSheet =
        when (field) {
            RecordField.Variant -> this
            RecordField.Qty -> copy(qty = text)
            RecordField.Supplier -> copy(supplier = text)
            RecordField.UnitCost -> copy(unitCost = text)
            RecordField.Note -> copy(note = text)
        }.copy(errors = errors - field, saveError = null)
}

/** Which way a type moves the stock as the server signs it: sales and shrinkage take away. */
fun StockMovementType.direction(): Int =
    when (this) {
        StockMovementType.SaleApp, StockMovementType.SaleOffline, StockMovementType.Shrinkage -> -1
        else -> 1
    }
