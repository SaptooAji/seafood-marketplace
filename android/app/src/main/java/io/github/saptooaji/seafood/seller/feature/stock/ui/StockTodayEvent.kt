package io.github.saptooaji.seafood.seller.feature.stock.ui

/** What the seller does on "Stok hari ini". */
sealed interface StockTodayEvent {
    data class Increase(
        val variantId: Long,
    ) : StockTodayEvent

    data class Decrease(
        val variantId: Long,
    ) : StockTodayEvent

    data class Typed(
        val variantId: Long,
        val text: String,
    ) : StockTodayEvent

    /** "Kembalikan": back to the server's count. */
    data class Revert(
        val variantId: Long,
    ) : StockTodayEvent

    data object Save : StockTodayEvent

    data object Retry : StockTodayEvent

    data object NoticeShown : StockTodayEvent
}

/** Test tags of "Stok hari ini". */
object StockTodayTags {
    const val SAVE = "stock_save"
    const val RETRY = "stock_retry"
    const val OPEN_PRODUCTS = "stock_open_products"

    fun row(id: Long) = "stock_row_$id"

    fun field(id: Long) = "stock_field_$id"

    fun increase(id: Long) = "stock_increase_$id"

    fun decrease(id: Long) = "stock_decrease_$id"

    fun revert(id: Long) = "stock_revert_$id"
}
