package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.feature.product.data.PageMetaDto
import io.github.saptooaji.seafood.seller.feature.product.data.UnitDto
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementPage
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import java.math.BigDecimal
import java.time.OffsetDateTime

/** `StockMovementResource::collection`, paginated: `data` and `meta`. */
@Serializable
data class StockMovementPageDto(
    val data: List<StockMovementDto>,
    val meta: PageMetaDto,
) {
    fun toDomain() = StockMovementPage(data.map { it.toDomain() }, meta.currentPage, meta.lastPage, meta.total)
}

/** One stock book line; decimals arrive as strings such as `"-2.500"`. */
@Serializable
data class StockMovementDto(
    val id: Long,
    val type: UnitDto,
    val qty: String,
    @SerialName("stock_after") val stockAfter: String,
    val variant: MovementVariantDto,
    val product: NamedDto,
    @SerialName("supplier_name") val supplierName: String? = null,
    @SerialName("unit_cost") val unitCost: Long? = null,
    val note: String? = null,
    @SerialName("created_by") val createdBy: NamedDto? = null,
    @SerialName("created_at") val createdAt: String? = null,
) {
    fun toDomain() =
        StockMovement(
            id = id,
            type = StockMovementType.from(type.value),
            typeLabel = type.label,
            qty = BigDecimal(qty),
            stockAfter = BigDecimal(stockAfter),
            variantId = variant.id,
            variantName = variant.name,
            unitLabel = variant.unit.label,
            productName = product.name,
            supplierName = supplierName,
            unitCost = unitCost,
            note = note,
            createdBy = createdBy?.name,
            createdAt = createdAt?.let { OffsetDateTime.parse(it).toInstant() },
        )
}

@Serializable
data class MovementVariantDto(
    val id: Long,
    val name: String,
    val unit: UnitDto,
)

@Serializable
data class NamedDto(
    val id: Long,
    val name: String,
)

/** The body of `POST seller/stock-movements` (`StockMovementRequest`); nulls are left out. */
@Serializable
data class StockMovementRequest(
    @SerialName("variant_id") val variantId: Long,
    val type: String,
    val qty: String,
    @SerialName("supplier_name") val supplierName: String? = null,
    @SerialName("unit_cost") val unitCost: Long? = null,
    val note: String? = null,
) {
    companion object {
        fun from(movement: NewStockMovement) =
            StockMovementRequest(
                variantId = movement.variantId,
                type = movement.type.value,
                qty = movement.qty.stripTrailingZeros().toPlainString(),
                supplierName = movement.supplierName,
                unitCost = movement.unitCost,
                note = movement.note,
            )
    }
}
