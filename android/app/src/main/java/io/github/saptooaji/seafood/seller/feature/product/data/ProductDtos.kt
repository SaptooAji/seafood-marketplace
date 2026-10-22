package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDetail
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantChanges
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
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

/** `ProductVariantResource`. Decimal columns arrive as strings, such as `"18.500"`. */
@Serializable
data class VariantDto(
    val id: Long,
    val name: String,
    val unit: UnitDto,
    val price: Long,
    @SerialName("min_qty") val minQty: String,
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
            unit = VariantUnit.from(unit.value),
            minQty = BigDecimal(minQty),
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

/**
 * The body of `POST` and `PUT seller/products`. Null fields are left out
 * (the JSON drops nulls), so a ready-stock product sends no pre-order
 * fields and an update sends no `variants`. Decimals go as strings so their
 * digits reach the server exactly.
 */
@Serializable
data class ProductRequest(
    @SerialName("category_id") val categoryId: Long,
    val name: String,
    val description: String,
    @SerialName("seo_description") val seoDescription: String?,
    val condition: String,
    @SerialName("sales_mode") val salesMode: String,
    @SerialName("daily_quota") val dailyQuota: String?,
    @SerialName("lead_time_days") val leadTimeDays: Int?,
    @SerialName("cutoff_time") val cutoffTime: String?,
    @SerialName("shelf_life_days") val shelfLifeDays: Int?,
    @SerialName("storage_instructions") val storageInstructions: String?,
    @SerialName("is_active") val isActive: Boolean,
    val attributes: Map<String, String>,
    val variants: List<NewVariantRequest>?,
) {
    companion object {
        fun from(
            draft: ProductDraft,
            variant: NewVariant?,
        ) = with(draft) {
            ProductRequest(
                categoryId = categoryId,
                name = name,
                description = description,
                seoDescription = seoDescription,
                condition = condition.value,
                salesMode = salesMode.value,
                dailyQuota = dailyQuota?.toPlainString(),
                leadTimeDays = leadTimeDays,
                cutoffTime = cutoffTime,
                shelfLifeDays = shelfLifeDays,
                storageInstructions = storageInstructions,
                isActive = isActive,
                attributes = attributes.mapKeys { it.key.toString() },
                variants = variant?.let { listOf(NewVariantRequest.from(it)) },
            )
        }
    }
}

/** A variant to add, alone or inside a new product; a null opening stock is left out. */
@Serializable
data class NewVariantRequest(
    val name: String,
    val unit: String,
    val price: Long,
    @SerialName("min_qty") val minQty: String,
    @SerialName("initial_stock") val initialStock: String?,
) {
    companion object {
        fun from(variant: NewVariant) =
            NewVariantRequest(
                name = variant.name,
                unit = variant.unit.value,
                price = variant.price,
                minQty = variant.minQty.toPlainString(),
                initialStock = variant.initialStock?.toPlainString(),
            )
    }
}

/**
 * The body of `PUT seller/variants/{id}`. It has no stock field, so the app
 * cannot send one. `is_active` is always sent, because the server reads a
 * missing flag as active.
 */
@Serializable
data class VariantChangesRequest(
    val name: String,
    val unit: String,
    val price: Long,
    @SerialName("min_qty") val minQty: String,
    @SerialName("is_active") val isActive: Boolean,
) {
    companion object {
        fun from(changes: VariantChanges) =
            VariantChangesRequest(
                name = changes.name,
                unit = changes.unit.value,
                price = changes.price,
                minQty = changes.minQty.toPlainString(),
                isActive = changes.isActive,
            )
    }
}

/** `ProductResource` as `GET seller/products/{id}` sends it, with the attribute values. */
@Serializable
data class ProductDetailDto(
    val id: Long,
    val name: String,
    val description: String,
    @SerialName("seo_description") val seoDescription: String? = null,
    val condition: UnitDto,
    @SerialName("sales_mode") val salesMode: UnitDto,
    @SerialName("daily_quota") val dailyQuota: String? = null,
    @SerialName("lead_time_days") val leadTimeDays: Int? = null,
    @SerialName("cutoff_time") val cutoffTime: String? = null,
    @SerialName("shelf_life_days") val shelfLifeDays: Int? = null,
    @SerialName("storage_instructions") val storageInstructions: String? = null,
    @SerialName("is_active") val isActive: Boolean,
    val blocked: Boolean,
    @SerialName("block_reason") val blockReason: String? = null,
    val category: CategoryDto,
    val variants: List<VariantDto>,
    val attributes: List<AttributeDto>? = null,
) {
    fun toDomain() =
        ProductDetail(
            id = id,
            draft =
                ProductDraft(
                    categoryId = category.id,
                    name = name,
                    description = description,
                    seoDescription = seoDescription,
                    condition = ProductCondition.from(condition.value) ?: ProductCondition.Fresh,
                    salesMode = SalesMode.from(salesMode.value),
                    dailyQuota = dailyQuota?.toBigDecimalOrNull(),
                    leadTimeDays = leadTimeDays,
                    cutoffTime = cutoffTime,
                    shelfLifeDays = shelfLifeDays,
                    storageInstructions = storageInstructions,
                    isActive = isActive,
                    attributes =
                        attributes
                            .orEmpty()
                            .mapNotNull { attribute -> attribute.value?.let { attribute.id to it } }
                            .toMap(),
                ),
            isBlocked = blocked,
            blockReason = blockReason,
            variants = variants.map { it.toDomain() },
        )
}
