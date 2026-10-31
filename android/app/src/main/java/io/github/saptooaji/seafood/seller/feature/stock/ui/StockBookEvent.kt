package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import java.time.LocalDate

/** What the seller does in the stock book and its sheets. */
sealed interface StockBookEvent {
    data object LoadMore : StockBookEvent

    data object Retry : StockBookEvent

    data object RetryLoadMore : StockBookEvent

    data object NoticeShown : StockBookEvent

    // --- The filter sheet ----------------------------------------------

    data object OpenFilter : StockBookEvent

    data object CloseFilter : StockBookEvent

    /** Null is "Semua jenis". */
    data class FilterTypePicked(
        val type: StockMovementType?,
    ) : StockBookEvent

    /** Null is "Semua ukuran". */
    data class FilterVariantPicked(
        val variantId: Long?,
    ) : StockBookEvent

    data class FilterDatesChanged(
        val from: LocalDate?,
        val to: LocalDate?,
    ) : StockBookEvent

    data object ApplyFilter : StockBookEvent

    /** Also the empty state's "Hapus saringan". */
    data object ClearFilter : StockBookEvent

    // --- The record sheet ----------------------------------------------

    data object OpenRecord : StockBookEvent

    data object CloseRecord : StockBookEvent

    data class RecordTypePicked(
        val type: StockMovementType,
    ) : StockBookEvent

    data object OpenVariantPicker : StockBookEvent

    data object CloseVariantPicker : StockBookEvent

    data class RecordVariantPicked(
        val variantId: Long,
    ) : StockBookEvent

    data class RecordTextChanged(
        val field: RecordField,
        val text: String,
    ) : StockBookEvent

    data object SaveRecord : StockBookEvent
}

/** Test tags of the stock book and its sheets. */
@Suppress("TooManyFunctions") // One tag builder per repeated element.
object StockBookTags {
    const val BACK = "stock_book_back"
    const val LIST = "stock_book_list"
    const val ADD = "stock_book_add"
    const val DATE_CHIP = "stock_book_date_chip"
    const val TYPE_CHIP = "stock_book_type_chip"
    const val VARIANT_CHIP = "stock_book_variant_chip"
    const val CLEAR_FILTER = "stock_book_clear_filter"
    const val RETRY = "stock_book_retry"
    const val RETRY_MORE = "stock_book_retry_more"
    const val FILTER_VARIANT = "stock_filter_variant"
    const val FILTER_FROM = "stock_filter_from"
    const val FILTER_TO = "stock_filter_to"
    const val APPLY_FILTER = "stock_filter_apply"
    const val CLOSE_FILTER = "stock_filter_close"
    const val RECORD_VARIANT = "stock_record_variant"
    const val RECORD_QTY = "stock_record_qty"
    const val RECORD_SUPPLIER = "stock_record_supplier"
    const val RECORD_COST = "stock_record_cost"
    const val RECORD_NOTE = "stock_record_note"
    const val SAVE_RECORD = "stock_record_save"
    const val CLOSE_RECORD = "stock_record_close"
    const val CLOSE_PICKER = "stock_record_picker_close"

    fun row(id: Long) = "stock_book_row_$id"

    /** Null is "Semua jenis". */
    fun filterType(type: StockMovementType?) = "stock_filter_type_${type?.value ?: "all"}"

    fun filterVariant(id: Long?) = "stock_filter_variant_${id ?: "all"}"

    fun recordType(type: StockMovementType) = "stock_record_type_${type.value}"

    fun pickVariant(id: Long) = "stock_record_pick_$id"
}
