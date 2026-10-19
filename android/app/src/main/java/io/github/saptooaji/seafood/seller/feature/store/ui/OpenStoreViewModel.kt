package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.location.DeviceLocation
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Opening a store: the profile, the pin, and the bank account. The profile
 * and the pin are kept in the [SavedStateHandle] (rotation, process death);
 * the bank account lives in memory only, so it is never written to the device.
 */
@HiltViewModel
class OpenStoreViewModel
    @Inject
    constructor(
        private val repository: StoreRepository,
        private val deviceLocation: DeviceLocation,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(restored())
        val uiState: StateFlow<OpenStoreUiState> = _uiState.asStateFlow()

        private fun restored(): OpenStoreUiState {
            var form = StoreFormState()
            for ((field, key) in SAVED_FIELDS) {
                savedStateHandle.get<String>(key)?.let { form = form.with(field, it) }
            }
            val lat = savedStateHandle.get<Double>(KEY_PIN_LAT)
            val lng = savedStateHandle.get<Double>(KEY_PIN_LNG)
            val pin = if (lat != null && lng != null) GeoPoint(lat, lng) else null
            if (pin != null) form = form.withPin(pin)
            return OpenStoreUiState(form = form, camera = MapCamera(pin ?: GeoPoint.JAKARTA_UTARA))
        }

        fun onEvent(event: OpenStoreEvent) {
            when (event) {
                is OpenStoreEvent.FieldChanged -> {
                    SAVED_FIELDS[event.field]?.let { savedStateHandle[it] = event.value }
                    _uiState.update { it.copy(form = it.form.with(event.field, event.value), generalError = null) }
                }
                is OpenStoreEvent.PinMoved -> placePin(event.point)
                OpenStoreEvent.LocationGranted -> locate()
                OpenStoreEvent.LocationDenied -> {
                    flyTo(GeoPoint.JAKARTA_UTARA)
                    _uiState.update { it.copy(locationMessage = UiText.Resource(R.string.store_location_denied)) }
                }
                OpenStoreEvent.Submit -> submit()
            }
        }

        private fun placePin(point: GeoPoint) {
            savedStateHandle[KEY_PIN_LAT] = point.lat
            savedStateHandle[KEY_PIN_LNG] = point.lng
            _uiState.update { it.copy(form = it.form.withPin(point)) }
        }

        /** Moves the camera and the pin together; the map reports the same point when it stops. */
        private fun flyTo(point: GeoPoint) {
            placePin(point)
            _uiState.update { it.copy(camera = MapCamera(point, it.camera.move + 1)) }
        }

        private fun locate() {
            viewModelScope.launch {
                val point = deviceLocation.current()
                if (point != null) {
                    flyTo(point)
                    _uiState.update { it.copy(locationMessage = null) }
                } else {
                    _uiState.update { it.copy(locationMessage = UiText.Resource(R.string.store_location_unavailable)) }
                }
            }
        }

        private fun submit() {
            val state = _uiState.value
            if (!state.canSubmit) return

            val (checked, details) = state.form.validated()
            if (details == null) {
                _uiState.update { it.copy(form = checked) }
                return
            }

            _uiState.update { it.copy(form = checked, isSubmitting = true, generalError = null, alreadyHasStore = false) }
            viewModelScope.launch {
                val result = repository.open(details)
                _uiState.update { it.copy(isSubmitting = false) }
                handle(result)
            }
        }

        private fun handle(result: ApiResult<Store>) {
            when (result) {
                is ApiResult.Success -> _uiState.update { it.copy(opened = true) }
                is ApiResult.Conflict -> _uiState.update { it.copy(alreadyHasStore = true) }
                is ApiResult.Invalid ->
                    _uiState.update { state ->
                        state.form.withServerErrors(result.fieldErrors)?.let { state.copy(form = it) }
                            ?: state.copy(generalError = UiText.Resource(R.string.error_server))
                    }
                ApiResult.NetworkError -> _uiState.update { it.copy(generalError = UiText.Resource(R.string.error_network)) }
                else -> _uiState.update { it.copy(generalError = UiText.Resource(R.string.error_server)) }
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
