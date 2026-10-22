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

/** A variant to add: the first one of a new product, or one more of a saved product. */
data class NewVariant(
    val name: String,
    val unit: VariantUnit,
    /** Whole rupiah. */
    val price: Long,
    val minQty: BigDecimal,
    val initialStock: BigDecimal?,
)

/**
 * The details of a saved variant (`PUT seller/variants/{id}`). It has no
 * stock: stock only changes through the stock book.
 */
data class VariantChanges(
    val name: String,
    val unit: VariantUnit,
    /** Whole rupiah. */
    val price: Long,
    val minQty: BigDecimal,
    val isActive: Boolean,
)

/** A product as the edit form loads it (`GET seller/products/{id}`). */
data class ProductDetail(
    val id: Long,
    val draft: ProductDraft,
    val isBlocked: Boolean,
    val blockReason: String?,
    val variants: List<ProductVariant>,
    /** In their order; the first is the main photo (#241). */
    val images: List<ProductImage> = emptyList(),
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
    ;

    companion object {
        fun from(value: String): VariantUnit = entries.firstOrNull { it.value == value } ?: Kg
    }
}
