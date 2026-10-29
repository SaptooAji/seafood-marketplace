package io.github.saptooaji.seafood.seller.feature.stock.domain

import java.math.BigDecimal
import java.time.Instant
import java.time.LocalDate

/** Why the stock changed (`StockMovementType` on the server). */
enum class StockMovementType(
    val value: String,
) {
    StockIn("stock_in"),
    SaleApp("sale_app"),
    SaleOffline("sale_offline"),
    Shrinkage("shrinkage"),
    Adjustment("adjustment"),
    CancelReturn("cancel_return"),
    ;

    companion object {
        /** What a seller may write; app sales and cancel returns come from orders only. */
        val recordable: List<StockMovementType> = listOf(StockIn, SaleOffline, Shrinkage, Adjustment)

        /** Null for a type added on the server after this app version; its label still shows. */
        fun from(value: String): StockMovementType? = entries.firstOrNull { it.value == value }
    }
}

/**
 * One line of the stock book. [qty] is signed and [stockAfter] is the stock
 * right after, both in the variant's unit. [createdBy] is null for a line the
 * system wrote.
 */
data class StockMovement(
    val id: Long,
    val type: StockMovementType?,
    /** The server's label, such as `Stok Masuk`. */
    val typeLabel: String,
    val qty: BigDecimal,
    val stockAfter: BigDecimal,
    val variantId: Long,
    val variantName: String,
    val unitLabel: String,
    val productName: String,
    val supplierName: String?,
    /** Rupiah per unit, for stock in. */
    val unitCost: Long?,
    val note: String?,
    val createdBy: String?,
    val createdAt: Instant?,
)

data class StockMovementPage(
    val movements: List<StockMovement>,
    val currentPage: Int,
    val lastPage: Int,
    val total: Int,
)

/** The stock book's filters; null means any. [from] and [to] are both included. */
data class StockFilter(
    val variantId: Long? = null,
    val type: StockMovementType? = null,
    val from: LocalDate? = null,
    val to: LocalDate? = null,
) {
    val isEmpty: Boolean get() = this == StockFilter()
}

/**
 * A change the seller records. [qty] is positive and the server gives it the
 * type's sign, except for an adjustment, which may be negative. The supplier
 * and cost are for stock in only.
 */
data class NewStockMovement(
    val variantId: Long,
    val type: StockMovementType,
    val qty: BigDecimal,
    val supplierName: String? = null,
    val unitCost: Long? = null,
    val note: String? = null,
)
