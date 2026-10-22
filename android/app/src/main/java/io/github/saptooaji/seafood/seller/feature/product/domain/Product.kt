package io.github.saptooaji.seafood.seller.feature.product.domain

import java.math.BigDecimal

/** A product of the seller's store, as `ProductResource` sends it. */
data class Product(
    val id: Long,
    val name: String,
    val categoryName: String,
    /** The seller's own switch; a blocked product stays blocked whatever it says. */
    val isActive: Boolean,
    val isBlocked: Boolean,
    /** Written by an admin; shown as plain text. */
    val blockReason: String?,
    val variants: List<ProductVariant>,
    val images: List<ProductImage>,
) {
    val status: ProductStatus
        get() =
            when {
                isBlocked -> ProductStatus.Blocked
                isActive -> ProductStatus.Active
                else -> ProductStatus.Inactive
            }

    /** The cheapest active variant, or null when none is active. */
    val priceFrom: PriceFrom?
        get() {
            val active = variants.filter { it.isActive }
            val cheapest = active.minByOrNull { it.price } ?: return null
            return PriceFrom(price = cheapest.price, unitLabel = cheapest.unitLabel, activeVariants = active.size)
        }

    /** Some variant's stock was last confirmed too long ago. */
    val hasStaleStock: Boolean get() = variants.any { it.isStockStale }

    val thumbnailUrl: String? get() = images.firstOrNull()?.thumbnailUrl
}

/** "Mulai Rp45.000 / kg": [activeVariants] tells whether "Mulai" belongs in front. */
data class PriceFrom(
    val price: Long,
    val unitLabel: String,
    val activeVariants: Int,
)

data class ProductVariant(
    val id: Long,
    val name: String,
    /** The server's label for the unit, such as `Kg` or `Pcs`. */
    val unitLabel: String,
    /** Whole rupiah. */
    val price: Long,
    val stock: BigDecimal,
    val isStockStale: Boolean,
    val isActive: Boolean,
    val unit: VariantUnit,
    /** The least a buyer may order, in [unit]. */
    val minQty: BigDecimal,
)

data class ProductImage(
    val id: Long,
    val url: String,
    val thumbnailUrl: String,
)

enum class ProductStatus { Active, Inactive, Blocked }

/** The status chips of the list, with the `status` the API filters by. */
enum class ProductStatusFilter(
    val query: String?,
) {
    All(null),
    Active("active"),
    Inactive("inactive"),
    Blocked("blocked"),
}

/** One page of the list, with Laravel's paging (`meta`). */
data class ProductPage(
    val products: List<Product>,
    val currentPage: Int,
    val lastPage: Int,
    val total: Int,
)
