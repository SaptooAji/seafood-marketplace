package io.github.saptooaji.seafood.seller.feature.store.ui

import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint

data class OpenStoreUiState(
    val form: StoreFormState = StoreFormState(),
    val camera: MapCamera = MapCamera(GeoPoint.JAKARTA_UTARA),
    /** Why the map is not at the device: the permission was denied, or there was no fix. */
    val locationMessage: UiText? = null,
    /** An error that belongs to no field: no connection or a server error. */
    val generalError: UiText? = null,
    val isSubmitting: Boolean = false,
    /** The server answered 409: this seller already owns a store. */
    val alreadyHasStore: Boolean = false,
    /** The store was opened and waits for an admin; the screen moves on to its status. */
    val opened: Boolean = false,
) {
    val canSubmit: Boolean get() = !isSubmitting
}

/**
 * Where the map should be. [move] counts the times the view model asked the
 * map to fly to [target]; at 0 the map only starts there.
 */
data class MapCamera(
    val target: GeoPoint,
    val move: Int = 0,
)

sealed interface OpenStoreEvent {
    data class FieldChanged(
        val field: StoreField,
        val value: String,
    ) : OpenStoreEvent

    /** The map stopped with [point] under the pin. */
    data class PinMoved(
        val point: GeoPoint,
    ) : OpenStoreEvent

    /** The location permission is granted: centre the map on the device. */
    data object LocationGranted : OpenStoreEvent

    data object LocationDenied : OpenStoreEvent

    data object Submit : OpenStoreEvent
}

/** Test tags of the store form, shared by opening and editing a store. */
object StoreFormTags {
    const val NAME = "store_name"
    const val DESCRIPTION = "store_description"
    const val PHONE = "store_phone"
    const val ADDRESS = "store_address"
    const val CITY = "store_city"
    const val PIN = "store_pin"
    const val MY_LOCATION = "store_my_location"
    const val BANK_NAME = "store_bank_name"
    const val BANK_ACCOUNT_NO = "store_bank_account_no"
    const val BANK_ACCOUNT_NAME = "store_bank_account_name"
}

/** Test tags of the open-store screen. */
object OpenStoreTags {
    const val SUBMIT = "open_store_submit"
    const val STORE_STATUS = "open_store_status"
}
