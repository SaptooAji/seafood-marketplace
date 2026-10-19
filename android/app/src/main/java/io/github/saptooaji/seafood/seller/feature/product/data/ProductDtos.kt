package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.math.BigDecimal

/** A paginated `ProductResource` collection: `data` and Laravel's `meta`. */
@Serializable
data class ProductPageDto(
    val data: List<ProductDto>,
    val meta: PageMetaDto,
) {
    fun toDomain() =
        ProductPage(
            products = data.map { it.toDomain() },
            currentPage = meta.currentPage,
            lastPage = meta.lastPage,
            total = meta.total,
        )
}

@Serializable
data class PageMetaDto(
    @SerialName("current_page") val currentPage: Int,
    @SerialName("last_page") val lastPage: Int,
    val total: Int,
)

/** The fields of `ProductResource` the list uses; the rest are ignored. */
@Serializable
data class ProductDto(
    val id: Long,
    val name: String,
    @SerialName("is_active") val isActive: Boolean,
    val blocked: Boolean,
    @SerialName("block_reason") val blockReason: String? = null,
    val category: CategoryDto,
    val variants: List<VariantDto>,
    val images: List<ImageDto>,
) {
    fun toDomain() =
        Product(
            id = id,
            name = name,
            categoryName = category.name,
            isActive = isActive,
            isBlocked = blocked,
            blockReason = blockReason,
            variants = variants.map { it.toDomain() },
            images = images.map { ProductImage(it.id, it.url, it.thumbnailUrl) },
        )
}

@Serializable
data class CategoryDto(
    val id: Long,
    val name: String,
)

/** `ProductVariantResource`. Decimal columns arrive as strings, such as `"18.500"`. */
@Serializable
data class VariantDto(
    val id: Long,
    val name: String,
    val unit: UnitDto,
    val price: Long,
    val stock: String,
    @SerialName("is_stock_stale") val isStockStale: Boolean,
    @SerialName("is_active") val isActive: Boolean,
) {
    fun toDomain() =
        ProductVariant(
            id = id,
            name = name,
            unitLabel = unit.label,
            price = price,
            stock = BigDecimal(stock),
            isStockStale = isStockStale,
            isActive = isActive,
        )
}

@Serializable
data class UnitDto(
    val value: String,
    val label: String,
)

@Serializable
data class ImageDto(
    val id: Long,
    val url: String,
    @SerialName("thumbnail_url") val thumbnailUrl: String,
)
