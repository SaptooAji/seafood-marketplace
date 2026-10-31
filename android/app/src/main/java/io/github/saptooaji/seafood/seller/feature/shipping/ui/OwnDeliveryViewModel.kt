package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * The Antar sendiri form (#246): adds own delivery when the store has none,
 * or changes its radius, fees, and switch. The server checks the same rules
 * again in `SaveShippingMethod`; its messages are shown as sent.
 */
@HiltViewModel
class OwnDeliveryViewModel
    @Inject
    constructor(
        private val methods: ShippingMethodRepository,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(OwnDeliveryUiState())
        val uiState: StateFlow<OwnDeliveryUiState> = _uiState.asStateFlow()

        /** The saved method being changed; null while adding one. */
        private var existing: ShippingMethod? = null

        init {
            load()
        }

        fun onEvent(event: OwnDeliveryEvent) {
            when (event) {
                is OwnDeliveryEvent.FieldChanged -> edit { it.with(event.field, event.value) }
                is OwnDeliveryEvent.FeeTypePicked -> edit { it.copy(feeType = event.feeType) }
                is OwnDeliveryEvent.ActiveChanged -> edit { it.copy(isActive = event.active, errors = it.errors - OwnDeliveryField.Active) }
                OwnDeliveryEvent.Save -> save()
                OwnDeliveryEvent.Retry -> load()
            }
        }

        private fun edit(change: (OwnDeliveryForm) -> OwnDeliveryForm) {
            _uiState.update { it.copy(form = change(it.form), generalError = null) }
        }

        private fun load() {
            _uiState.update { it.copy(isLoading = true, trouble = null) }
            viewModelScope.launch {
                val result = methods.list()
                _uiState.update { state ->
                    when (result) {
                        is ApiResult.Success -> {
                            val own = result.data.firstOrNull { it.type == ShippingType.OwnDelivery }
                            existing = own
                            state.copy(isLoading = false, isNew = own == null, form = own?.let(OwnDeliveryForm::from) ?: OwnDeliveryForm())
                        }
                        // AuthInterceptor has ended the session; the navigation is on its way to sign-in.
                        ApiResult.Unauthorized -> state
                        else -> state.copy(isLoading = false, trouble = ConnectionTrouble.of(result))
                    }
                }
            }
        }

        private fun save() {
            val state = _uiState.value
            if (state.isSaving || state.isLoading || state.trouble != null) return

            val (checked, input) = state.form.validated()
            _uiState.update { it.copy(form = checked, isSaving = input != null, generalError = null) }
            if (input == null) return

            viewModelScope.launch {
                val saved = existing
                val result =
                    if (saved == null) {
                        methods.add(input)
                    } else {
                        methods.update(saved.id, input.copy(isActive = checked.isActive))
                    }
                _uiState.update { current -> answered(current.copy(isSaving = false), result) }
            }
        }

        private fun answered(
            state: OwnDeliveryUiState,
            result: ApiResult<ShippingMethod>,
        ): OwnDeliveryUiState =
            when (result) {
                is ApiResult.Success -> state.copy(result = OwnDeliveryResult.Saved)
                is ApiResult.Forbidden -> state.copy(result = OwnDeliveryResult.NotOwner)
                is ApiResult.Invalid ->
                    state.form.withServerErrors(result.fieldErrors)?.let { state.copy(form = it) }
                        ?: state.copy(generalError = result.message?.let { UiText.Raw(it) } ?: UiText.Resource(R.string.error_server))
                ApiResult.NetworkError -> state.copy(generalError = UiText.Resource(R.string.error_network))
                ApiResult.Unauthorized -> state
                else -> state.copy(generalError = UiText.Resource(R.string.error_server))
            }
    }
