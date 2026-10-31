package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.feature.product.data.UnitDto
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.math.BigDecimal
import java.time.OffsetDateTime

/** `DailyStockResource`: a product with only its variants to count. */
@Serializable
data class StockProductDto(
    val id: Long,
    val name: String,
    val variants: List<StockVariantDto>,
) {
    fun toDomain() = StockProduct(id, name, variants.map { it.toDomain() })
}

/** Stock is a `decimal(10,3)` string such as `"18.500"`; the time is ISO 8601 with an offset. */
@Serializable
data class StockVariantDto(
    val id: Long,
    val name: String,
    val unit: UnitDto,
    val stock: String,
    @SerialName("stock_confirmed_at") val stockConfirmedAt: String? = null,
    @SerialName("is_stock_stale") val isStockStale: Boolean,
) {
    fun toDomain() =
        StockVariant(
            id = id,
            name = name,
            unit = VariantUnit.from(unit.value),
            unitLabel = unit.label,
            stock = BigDecimal(stock),
            confirmedAt = stockConfirmedAt?.let { OffsetDateTime.parse(it).toInstant() },
            isStale = isStockStale,
        )
}

/**
 * The body of `POST seller/stock/confirm` (`ConfirmDailyStockRequest`).
 * Without changes [variants] is null and left out; [confirmAll] has no
 * default, so it is always sent.
 */
@Serializable
data class ConfirmStockRequest(
    val variants: List<StockCountDto>?,
    @SerialName("confirm_all") val confirmAll: Boolean,
) {
    companion object {
        fun from(counts: List<StockCount>) =
            ConfirmStockRequest(
                variants = counts.map { StockCountDto(it.variantId, it.stock.stripTrailingZeros().toPlainString()) }.ifEmpty { null },
                confirmAll = true,
            )
    }
}

/** One count, as a plain decimal string: `7.5`, `12`, `0`. */
@Serializable
data class StockCountDto(
    val id: Long,
    val stock: String,
)
