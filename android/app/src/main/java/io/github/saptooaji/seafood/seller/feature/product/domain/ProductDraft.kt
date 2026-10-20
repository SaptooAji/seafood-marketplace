package io.github.saptooaji.seafood.seller.feature.product.domain

import java.math.BigDecimal

/** What the product form sends (`ProductRequest`), without variants. */
data class ProductDraft(
    val categoryId: Long,
    val name: String,
    val description: String,
    val seoDescription: String?,
    val condition: ProductCondition,
    val salesMode: SalesMode,
    /** Pre order only; null for ready stock. */
    val dailyQuota: BigDecimal?,
    val leadTimeDays: Int?,
    /** `HH:mm`, pre order only. */
    val cutoffTime: String?,
    val shelfLifeDays: Int?,
    val storageInstructions: String?,
    val isActive: Boolean,
    /** Non-empty values keyed by attribute id. */
    val attributes: Map<Long, String>,
)

/** The first variant of a new product; the full editor is #239. */
data class NewVariant(
    val name: String,
    val unit: VariantUnit,
    /** Whole rupiah. */
    val price: Long,
    val minQty: BigDecimal,
    val initialStock: BigDecimal?,
)

/** A product as the edit form loads it (`GET seller/products/{id}`). */
data class ProductDetail(
    val id: Long,
    val draft: ProductDraft,
    val isBlocked: Boolean,
    val blockReason: String?,
    val variants: List<ProductVariant>,
)

enum class ProductCondition(
    val value: String,
) {
    Live("live"),
    Fresh("fresh"),
    Frozen("frozen"),
    Marinated("marinated"),
    ;

    companion object {
        fun from(value: String): ProductCondition? = entries.firstOrNull { it.value == value }
    }
}

enum class SalesMode(
    val value: String,
) {
    ReadyStock("ready_stock"),
    PreOrder("pre_order"),
    ;

    companion object {
        fun from(value: String): SalesMode = entries.firstOrNull { it.value == value } ?: ReadyStock
    }
}

enum class VariantUnit(
    val value: String,
) {
    Kg("kg"),
    Piece("piece"),
    Dozen("dozen"),
    Pack("pack"),
}
