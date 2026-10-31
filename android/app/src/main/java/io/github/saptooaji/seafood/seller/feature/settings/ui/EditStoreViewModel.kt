package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.location.DeviceLocation
import io.github.saptooaji.seafood.seller.feature.store.ui.MapCamera
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreField
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormState
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.LocalTime
import javax.inject.Inject

data class EditStoreUiState(
    val isLoading: Boolean = true,
    /** Why the store could not load. */
    val trouble: ConnectionTrouble? = null,
    val form: StoreFormState = StoreFormState(),
    val camera: MapCamera = MapCamera(GeoPoint.JAKARTA_UTARA),
    val locationMessage: UiText? = null,
    /** An error that belongs to no field: no connection or a server error. */
    val generalError: UiText? = null,
    val isSaving: Boolean = false,
    /** How the form ended; set once, then the screen closes. */
    val result: EditStoreResult? = null,
)

sealed interface EditStoreEvent {
    data class FieldChanged(
        val field: StoreField,
        val value: String,
    ) : EditStoreEvent

    data class PinMoved(
        val point: GeoPoint,
    ) : EditStoreEvent

    data object LocationGranted : EditStoreEvent

    data object LocationDenied : EditStoreEvent

    data object Save : EditStoreEvent

    data object Retry : EditStoreEvent
}

/** Test tags of the store form screen. */
object EditStoreTags {
    const val SAVE = "edit_store_save"
    const val BACK = "edit_store_back"
    const val RETRY = "edit_store_retry"
}

/**
 * Editing the store (#245): the form of #234, filled from `GET seller/store`.
 * What was typed outside the bank account is kept in the [SavedStateHandle]
 * and wins over the server after process death; the bank account lives in
 * memory only, so it is never written to the device.
 */
@HiltViewModel
class EditStoreViewModel
    @Inject
    constructor(
        private val stores: StoreRepository,
        private val deviceLocation: DeviceLocation,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(EditStoreUiState())
        val uiState: StateFlow<EditStoreUiState> = _uiState.asStateFlow()

        /** The reminder set in Pengaturan; the form has no field for it, but `PUT` sends it. */
        private var reminder: LocalTime? = null

        init {
            load()
        }

        fun onEvent(event: EditStoreEvent) {
            when (event) {
                is EditStoreEvent.FieldChanged -> {
                    SAVED_FIELDS[event.field]?.let { savedStateHandle[it] = event.value }
                    _uiState.update { it.copy(form = it.form.with(event.field, event.value), generalError = null) }
                }
                is EditStoreEvent.PinMoved -> placePin(event.point)
                EditStoreEvent.LocationGranted -> locate()
                EditStoreEvent.LocationDenied ->
                    _uiState.update { it.copy(locationMessage = UiText.Resource(R.string.store_location_denied)) }
                EditStoreEvent.Save -> save()
                EditStoreEvent.Retry -> load()
            }
        }

        private fun load() {
            _uiState.update { it.copy(isLoading = true, trouble = null) }
            viewModelScope.launch {
                val result = stores.current()
                val store = ((result as? ApiResult.Success)?.data as? CurrentStore.Exists)?.store
                _uiState.update { state ->
                    when {
                        store != null -> filled(state, store)
                        // AuthInterceptor has ended the session; the navigation is on its way to sign-in.
                        result == ApiResult.Unauthorized -> state
                        else -> state.copy(isLoading = false, trouble = ConnectionTrouble.of(result))
                    }
                }
            }
        }

        private fun filled(
            state: EditStoreUiState,
            store: Store,
        ): EditStoreUiState {
            reminder = store.stockReminderTime
            var form =
                StoreFormState(
                    name = store.name,
                    description = store.description.orEmpty(),
                    phone = store.phone,
                    address = store.address,
                    city = store.city,
                    bankName = store.bank?.name.orEmpty(),
                    bankAccountNo = store.bank?.accountNo.orEmpty(),
                    bankAccountName = store.bank?.accountName.orEmpty(),
                )
            for ((field, key) in SAVED_FIELDS) {
                savedStateHandle.get<String>(key)?.let { form = form.with(field, it) }
            }
            val pin = savedPin() ?: store.pin()
            if (pin != null) form = form.withPin(pin)
            return state.copy(isLoading = false, trouble = null, form = form, camera = MapCamera(pin ?: GeoPoint.JAKARTA_UTARA))
        }

        private fun Store.pin(): GeoPoint? {
            val latitude = lat
            val longitude = lng
            return if (latitude != null && longitude != null) GeoPoint(latitude.toDouble(), longitude.toDouble()) else null
        }

        private fun savedPin(): GeoPoint? {
            val latitude = savedStateHandle.get<Double>(KEY_PIN_LAT)
            val longitude = savedStateHandle.get<Double>(KEY_PIN_LNG)
            return if (latitude != null && longitude != null) GeoPoint(latitude, longitude) else null
        }

        private fun placePin(point: GeoPoint) {
            savedStateHandle[KEY_PIN_LAT] = point.lat
            savedStateHandle[KEY_PIN_LNG] = point.lng
            _uiState.update { it.copy(form = it.form.withPin(point)) }
        }

        private fun locate() {
            viewModelScope.launch {
                val point = deviceLocation.current()
                if (point != null) {
                    placePin(point)
                    _uiState.update { it.copy(camera = MapCamera(point, it.camera.move + 1), locationMessage = null) }
                } else {
                    _uiState.update { it.copy(locationMessage = UiText.Resource(R.string.store_location_unavailable)) }
                }
            }
        }

        private fun save() {
            val state = _uiState.value
            if (state.isSaving || state.isLoading || state.trouble != null) return

            val (checked, details) = state.form.validated()
            _uiState.update { it.copy(form = checked, isSaving = details != null, generalError = null) }
            if (details == null) return

            viewModelScope.launch {
                val result = stores.update(details.copy(stockReminderTime = reminder))
                _uiState.update { current ->
                    val done = current.copy(isSaving = false)
                    when (result) {
                        is ApiResult.Success -> done.copy(result = EditStoreResult.Saved)
                        is ApiResult.Forbidden -> done.copy(result = EditStoreResult.NotOwner)
                        is ApiResult.Invalid ->
                            done.form.withServerErrors(result.fieldErrors)?.let { done.copy(form = it) }
                                ?: done.copy(generalError = UiText.Resource(R.string.error_server))
                        ApiResult.NetworkError -> done.copy(generalError = UiText.Resource(R.string.error_network))
                        ApiResult.Unauthorized -> done
                        else -> done.copy(generalError = UiText.Resource(R.string.error_server))
                    }
                }
            }
        }

        private companion object {
            /** The fields kept across process death; the bank account is left out on purpose. */
            val SAVED_FIELDS =
                mapOf(
                    StoreField.Name to "name",
                    StoreField.Description to "description",
                    StoreField.Phone to "phone",
                    StoreField.Address to "address",
                    StoreField.City to "city",
                )
            const val KEY_PIN_LAT = "pin_lat"
            const val KEY_PIN_LNG = "pin_lng"
        }
    }
