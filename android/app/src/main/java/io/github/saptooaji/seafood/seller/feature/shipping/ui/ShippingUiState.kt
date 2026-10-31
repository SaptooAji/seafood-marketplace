package io.github.saptooaji.seafood.seller.feature.shipping.ui

import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType

data class ShippingMethodsUiState(
    val isLoading: Boolean = true,
    val trouble: ConnectionTrouble? = null,
    /** One row per kind, in [ShippingType] order; [ShippingRow.method] is null when the store has none. */
    val rows: List<ShippingRow> = emptyList(),
    /** The server's role decides; staff see the list without any way to change it. */
    val isOwner: Boolean = false,
    /** The manual kind whose "Tambah" sheet is open. */
    val adding: ShippingType? = null,
    val isAdding: Boolean = false,
    val notice: ShippingNotice? = null,
)

data class ShippingRow(
    val type: ShippingType,
    val method: ShippingMethod?,
)

/** A message over the list, cleared once shown. */
sealed interface ShippingNotice {
    /** The server refused the change, such as turning off the last active method; shown as sent. */
    data class Refused(
        val message: String,
    ) : ShippingNotice

    data object OwnerOnly : ShippingNotice

    data object NoInternet : ShippingNotice

    data object Server : ShippingNotice
}

sealed interface ShippingMethodsEvent {
    data object Retry : ShippingMethodsEvent

    /** Back from the own delivery form after a save. */
    data object Reload : ShippingMethodsEvent

    /** Back from the own delivery form, which the server refused with 403. */
    data object NotOwner : ShippingMethodsEvent

    data class Toggle(
        val type: ShippingType,
        val active: Boolean,
    ) : ShippingMethodsEvent

    /** "Tambah" on a manual kind opens its sheet; own delivery has its own form. */
    data class Add(
        val type: ShippingType,
    ) : ShippingMethodsEvent

    data object ConfirmAdd : ShippingMethodsEvent

    data object CloseAdd : ShippingMethodsEvent

    data object NoticeShown : ShippingMethodsEvent
}

/** Test tags of Cara terima pesanan. */
object ShippingTags {
    const val EDIT_OWN_DELIVERY = "shipping_edit_own_delivery"
    const val BACK = "shipping_back"
    const val RETRY = "shipping_retry"
    const val CONFIRM_ADD = "shipping_confirm_add"
    const val CANCEL_ADD = "shipping_cancel_add"

    fun row(type: ShippingType) = "shipping_row_${type.value}"

    fun toggle(type: ShippingType) = "shipping_toggle_${type.value}"

    fun add(type: ShippingType) = "shipping_add_${type.value}"
}

enum class OwnDeliveryField { Radius, FlatFee, FeePerKm, MinFee, Active }

/**
 * The Antar sendiri form as typed. The fees of both fee types are kept while
 * switching between them; only the chosen type's fees are sent.
 */
data class OwnDeliveryForm(
    val radius: String = "",
    val feeType: ShippingFeeType = ShippingFeeType.Flat,
    val flatFee: String = "",
    val feePerKm: String = "",
    val minFee: String = "",
    val isActive: Boolean = true,
    val errors: Map<OwnDeliveryField, UiText> = emptyMap(),
) {
    companion object {
        /** The form of a saved method: plain digits, and the radius with a comma. */
        fun from(method: ShippingMethod) =
            OwnDeliveryForm(
                radius = method.maxRadiusKm?.let { ShippingInput.show(it) }.orEmpty(),
                feeType = method.feeType,
                flatFee = method.flatFee?.toString().orEmpty(),
                feePerKm = method.feePerKm?.toString().orEmpty(),
                minFee = method.minFee?.toString().orEmpty(),
                isActive = method.isActive,
            )
    }
}

enum class OwnDeliveryResult {
    Saved,

    /** The server answered 403: the seller is no longer the owner. */
    NotOwner,
}

data class OwnDeliveryUiState(
    val isLoading: Boolean = true,
    val trouble: ConnectionTrouble? = null,
    /** No own delivery yet: saving adds it, and it starts on. */
    val isNew: Boolean = false,
    val form: OwnDeliveryForm = OwnDeliveryForm(),
    val generalError: UiText? = null,
    val isSaving: Boolean = false,
    /** How the form ended; set once, then the screen closes. */
    val result: OwnDeliveryResult? = null,
)

sealed interface OwnDeliveryEvent {
    data class FieldChanged(
        val field: OwnDeliveryField,
        val value: String,
    ) : OwnDeliveryEvent

    data class FeeTypePicked(
        val feeType: ShippingFeeType,
    ) : OwnDeliveryEvent

    data class ActiveChanged(
        val active: Boolean,
    ) : OwnDeliveryEvent

    data object Save : OwnDeliveryEvent

    data object Retry : OwnDeliveryEvent
}

/** Test tags of the Antar sendiri form. */
object OwnDeliveryTags {
    const val RADIUS = "own_delivery_radius"
    const val FLAT_FEE = "own_delivery_flat_fee"
    const val FEE_PER_KM = "own_delivery_fee_per_km"
    const val MIN_FEE = "own_delivery_min_fee"
    const val CHOOSE_FLAT = "own_delivery_choose_flat"
    const val CHOOSE_PER_KM = "own_delivery_choose_per_km"
    const val ACTIVE = "own_delivery_active"
    const val SAVE = "own_delivery_save"
    const val BACK = "own_delivery_back"
    const val RETRY = "own_delivery_retry"
}
