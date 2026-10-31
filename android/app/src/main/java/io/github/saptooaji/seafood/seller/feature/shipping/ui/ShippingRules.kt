package io.github.saptooaji.seafood.seller.feature.shipping.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import java.math.BigDecimal

/** How the radius and fee fields are read, the same limits as `ShippingMethodRequest`. */
object ShippingInput {
    /** `decimal(6,2)`. */
    val MAX_RADIUS = BigDecimal("9999.99")

    /** The unsigned integer fee columns are capped here. */
    const val MAX_FEE = 100_000_000L

    private val RADIUS = Regex("^\\d+(\\.\\d{1,2})?$")
    private val DIGITS = Regex("^\\d{1,12}$")
    private val FEE_DECORATION = Regex("Rp|[\\s.]")

    /** "7,5" or "7.5" is 7.5; null when it is not a number with two decimals at most. */
    fun radius(typed: String): BigDecimal? {
        val normalized = typed.trim().replace(',', '.')
        return if (RADIUS.matches(normalized)) BigDecimal(normalized) else null
    }

    /** "10.000", "Rp 3.000", or "10000" is whole rupiah; null for anything else. */
    fun fee(typed: String): Long? {
        val digits = typed.replace(FEE_DECORATION, "")
        return if (DIGITS.matches(digits)) digits.toLong() else null
    }

    /** A saved radius for its field: "20.00" is "20", "7.50" is "7,5". */
    fun show(radius: BigDecimal): String = radius.stripTrailingZeros().toPlainString().replace('.', ',')
}

/** What a row of Cara terima pesanan says. */
object ShippingLabels {
    fun summary(method: ShippingMethod): UiText {
        val radius = method.maxRadiusKm?.let(IndonesianNumbers::decimal).orEmpty()
        return when (method.type) {
            ShippingType.Pickup -> UiText.Resource(R.string.shipping_summary_pickup)
            ShippingType.Ojol -> UiText.Resource(R.string.shipping_summary_ojol)
            ShippingType.Expedition -> UiText.Resource(R.string.shipping_summary_expedition)
            ShippingType.OwnDelivery ->
                if (method.feeType == ShippingFeeType.PerKm) {
                    UiText.Resource(R.string.shipping_summary_per_km, listOf(rupiah(method.feePerKm), rupiah(method.minFee), radius))
                } else {
                    UiText.Resource(R.string.shipping_summary_flat, listOf(rupiah(method.flatFee), radius))
                }
        }
    }

    /** A kind the store has not added yet, by what it would do. */
    fun about(type: ShippingType): UiText =
        when (type) {
            ShippingType.Pickup -> UiText.Resource(R.string.shipping_summary_pickup)
            ShippingType.OwnDelivery -> UiText.Resource(R.string.shipping_about_own_delivery)
            ShippingType.Ojol -> UiText.Resource(R.string.shipping_summary_ojol)
            ShippingType.Expedition -> UiText.Resource(R.string.shipping_summary_expedition)
        }

    private fun rupiah(amount: Long?) = IndonesianNumbers.rupiah(amount ?: 0)
}

/** "Yang dilihat pembeli": what a buyer this far from the stall pays. */
object ShippingPreview {
    /** The distances of the board VD-Antar-Sendiri. */
    val DISTANCES = listOf(2, 6, 15, 25)

    /** The fee for a buyer [km] away, or null when the form does not reach them or is not filled in. */
    fun fee(
        form: OwnDeliveryForm,
        km: Int,
    ): Long? {
        val radius = ShippingInput.radius(form.radius)
        val reaches = radius != null && radius.signum() > 0 && BigDecimal(km) <= radius
        return when {
            !reaches -> null
            form.feeType == ShippingFeeType.Flat -> ShippingInput.fee(form.flatFee)
            else -> perKm(form, km)
        }
    }

    private fun perKm(
        form: OwnDeliveryForm,
        km: Int,
    ): Long? {
        val perKm = ShippingInput.fee(form.feePerKm)
        val minimum = ShippingInput.fee(form.minFee) ?: 0
        return perKm?.let { maxOf(it * km, minimum) }
    }
}

/** [value] typed into [field], whose error clears. */
fun OwnDeliveryForm.with(
    field: OwnDeliveryField,
    value: String,
): OwnDeliveryForm {
    val changed =
        when (field) {
            OwnDeliveryField.Radius -> copy(radius = value)
            OwnDeliveryField.FlatFee -> copy(flatFee = value)
            OwnDeliveryField.FeePerKm -> copy(feePerKm = value)
            OwnDeliveryField.MinFee -> copy(minFee = value)
            OwnDeliveryField.Active -> this
        }
    return changed.copy(errors = errors - field)
}

/**
 * The rules of `SaveShippingMethod` for own delivery, checked before sending:
 * the form with its errors, and what to send when there is none. A new
 * method leaves [ShippingMethodInput.isActive] to the server.
 */
fun OwnDeliveryForm.validated(): Pair<OwnDeliveryForm, ShippingMethodInput?> {
    val km = ShippingInput.radius(radius)
    val flat = ShippingInput.fee(flatFee)?.takeIf { it <= ShippingInput.MAX_FEE }
    val perKm = ShippingInput.fee(feePerKm)?.takeIf { it in 1..ShippingInput.MAX_FEE }
    val minimum = ShippingInput.fee(minFee)?.takeIf { it <= ShippingInput.MAX_FEE }
    val errors =
        buildMap {
            when {
                km == null || km.signum() <= 0 -> put(OwnDeliveryField.Radius, UiText.Resource(R.string.shipping_error_radius))
                km > ShippingInput.MAX_RADIUS -> put(OwnDeliveryField.Radius, UiText.Resource(R.string.shipping_error_radius_max))
            }
            if (feeType == ShippingFeeType.Flat) {
                if (flat == null) put(OwnDeliveryField.FlatFee, UiText.Resource(R.string.shipping_error_fee))
            } else {
                if (perKm == null) put(OwnDeliveryField.FeePerKm, UiText.Resource(R.string.shipping_error_fee_per_km))
                if (minimum == null) put(OwnDeliveryField.MinFee, UiText.Resource(R.string.shipping_error_fee))
            }
        }
    val input =
        ShippingMethodInput(
            type = ShippingType.OwnDelivery,
            feeType = feeType,
            maxRadiusKm = km,
            flatFee = flat.takeIf { feeType == ShippingFeeType.Flat },
            feePerKm = perKm.takeIf { feeType == ShippingFeeType.PerKm },
            minFee = minimum.takeIf { feeType == ShippingFeeType.PerKm },
        )
    return copy(errors = errors) to input.takeIf { errors.isEmpty() }
}

/**
 * The fields a 422 names, each with the server's own message (these rules
 * answer in Indonesian). A refused switch is turned back on. Null when the
 * 422 names no field of the form.
 */
fun OwnDeliveryForm.withServerErrors(fieldErrors: Map<String, List<String>>): OwnDeliveryForm? {
    val errors =
        SERVER_FIELDS
            .mapNotNull { (key, field) -> fieldErrors[key]?.firstOrNull()?.let { field to UiText.Raw(it) } }
            .toMap()
    return when {
        errors.isEmpty() -> null
        OwnDeliveryField.Active in errors -> copy(errors = this.errors + errors, isActive = true)
        else -> copy(errors = this.errors + errors)
    }
}

private val SERVER_FIELDS =
    mapOf(
        "max_radius_km" to OwnDeliveryField.Radius,
        "flat_fee" to OwnDeliveryField.FlatFee,
        "fee_per_km" to OwnDeliveryField.FeePerKm,
        "min_fee" to OwnDeliveryField.MinFee,
        "is_active" to OwnDeliveryField.Active,
    )
