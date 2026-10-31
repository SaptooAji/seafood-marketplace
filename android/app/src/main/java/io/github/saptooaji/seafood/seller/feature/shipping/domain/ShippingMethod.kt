package io.github.saptooaji.seafood.seller.feature.shipping.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import java.math.BigDecimal

/** `ShippingMethodType` on the server, in the order the list shows them. */
enum class ShippingType(
    val value: String,
) {
    Pickup("pickup"),
    OwnDelivery("own_delivery"),
    Ojol("ojol"),
    Expedition("expedition"),
    ;

    companion object {
        /** Null for a kind added on the server after this app version. */
        fun from(value: String): ShippingType? = entries.firstOrNull { it.value == value }
    }
}

/** `ShippingFeeType` on the server. */
enum class ShippingFeeType(
    val value: String,
) {
    Flat("flat"),
    PerKm("per_km"),

    /** Ojol and expedition: the seller fills the fee in per order (Tahap 5). */
    Manual("manual"),
    ;

    companion object {
        fun from(value: String): ShippingFeeType? = entries.firstOrNull { it.value == value }
    }
}

/** One of the store's shipping methods (`ShippingMethodResource`). Fees are whole rupiah. */
data class ShippingMethod(
    val id: Long,
    val type: ShippingType,
    val feeType: ShippingFeeType,
    val maxRadiusKm: BigDecimal? = null,
    val flatFee: Long? = null,
    val feePerKm: Long? = null,
    val minFee: Long? = null,
    val isActive: Boolean,
) {
    /** Every field of this method, as `PUT` needs it: the server writes every fee column. */
    fun toInput(isActive: Boolean = this.isActive) =
        ShippingMethodInput(
            type = type,
            feeType = feeType,
            maxRadiusKm = maxRadiusKm,
            flatFee = flatFee,
            feePerKm = feePerKm,
            minFee = minFee,
            isActive = isActive,
        )
}

/**
 * What is sent to add or change a method. A null field is left out of the
 * request, so the fees the fee type does not use are never sent; a null
 * [isActive] lets the server start a new method on.
 */
data class ShippingMethodInput(
    val type: ShippingType,
    val feeType: ShippingFeeType,
    val maxRadiusKm: BigDecimal? = null,
    val flatFee: Long? = null,
    val feePerKm: Long? = null,
    val minFee: Long? = null,
    val isActive: Boolean? = null,
)

/** The store's shipping methods. Every member may read them; only the owner may change them. */
interface ShippingMethodRepository {
    /** `GET seller/shipping-methods`; kinds this app does not know are left out. */
    suspend fun list(): ApiResult<List<ShippingMethod>>

    /** `POST seller/shipping-methods`, with the type. */
    suspend fun add(input: ShippingMethodInput): ApiResult<ShippingMethod>

    /** `PUT seller/shipping-methods/{id}`, without the type, which cannot change. */
    suspend fun update(
        id: Long,
        input: ShippingMethodInput,
    ): ApiResult<ShippingMethod>
}
