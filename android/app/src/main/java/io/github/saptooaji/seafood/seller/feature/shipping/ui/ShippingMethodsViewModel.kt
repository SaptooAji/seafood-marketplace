package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import kotlinx.coroutines.Job
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import javax.inject.Inject

/**
 * Cara terima pesanan (#246). A switch moves at once and goes back when the
 * server refuses; changes are sent one at a time, in the order they were
 * made, so the server always sees the last one last. The server decides who
 * may change a method (`StoreShippingMethodPolicy`) and that one stays active.
 */
@HiltViewModel
class ShippingMethodsViewModel
    @Inject
    constructor(
        private val methods: ShippingMethodRepository,
        private val stores: StoreRepository,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(ShippingMethodsUiState())
        val uiState: StateFlow<ShippingMethodsUiState> = _uiState.asStateFlow()

        /** One change at a time, in order. */
        private val sending = Mutex()
        private var loading: Job? = null

        init {
            load()
        }

        fun onEvent(event: ShippingMethodsEvent) {
            when (event) {
                ShippingMethodsEvent.Retry, ShippingMethodsEvent.Reload -> load()
                ShippingMethodsEvent.NotOwner -> {
                    _uiState.update { it.copy(notice = ShippingNotice.OwnerOnly) }
                    load()
                }
                is ShippingMethodsEvent.Toggle -> toggle(event.type, event.active)
                is ShippingMethodsEvent.Add ->
                    _uiState.update { state ->
                        val manual = event.type == ShippingType.Ojol || event.type == ShippingType.Expedition
                        if (state.isOwner && manual) state.copy(adding = event.type) else state
                    }
                ShippingMethodsEvent.ConfirmAdd -> confirmAdd()
                ShippingMethodsEvent.CloseAdd -> _uiState.update { it.copy(adding = null) }
                ShippingMethodsEvent.NoticeShown -> _uiState.update { it.copy(notice = null) }
            }
        }

        private fun load() {
            loading?.cancel()
            loading =
                viewModelScope.launch {
                    val list = async { methods.list() }
                    val store = async { stores.current() }
                    val (listResult, storeResult) = list.await() to store.await()
                    _uiState.update { shown(it, listResult, storeResult) }
                }
        }

        private fun shown(
            state: ShippingMethodsUiState,
            list: ApiResult<List<ShippingMethod>>,
            store: ApiResult<CurrentStore>,
        ): ShippingMethodsUiState {
            val current = ((store as? ApiResult.Success)?.data as? CurrentStore.Exists)?.store
            val failure = listOf(list, store).firstOrNull { it !is ApiResult.Success }
            return when {
                // AuthInterceptor has ended the session; the navigation is on its way to sign-in.
                failure == ApiResult.Unauthorized -> state
                list is ApiResult.Success && current != null ->
                    state.copy(
                        isLoading = false,
                        trouble = null,
                        rows = ShippingType.entries.map { type -> ShippingRow(type, list.data.firstOrNull { it.type == type }) },
                        isOwner = current.role == StoreRole.Owner,
                    )
                state.rows.isNotEmpty() -> state.copy(notice = noticeFor(failure ?: ApiResult.ServerError))
                else -> state.copy(isLoading = false, trouble = ConnectionTrouble.of(failure ?: ApiResult.ServerError))
            }
        }

        private fun toggle(
            type: ShippingType,
            active: Boolean,
        ) {
            val state = _uiState.value
            val method =
                state.rows
                    .firstOrNull { it.type == type }
                    ?.method
                    ?.takeIf { state.isOwner && it.isActive != active } ?: return

            put(method.copy(isActive = active))
            viewModelScope.launch {
                sending.withLock {
                    val result = methods.update(method.id, method.toInput(isActive = active))
                    if (result is ApiResult.Success) {
                        put(result.data)
                    } else {
                        refused(method, result)
                    }
                }
            }
        }

        /** Puts the switch back as it was before the refused change, and says why. */
        private fun refused(
            before: ShippingMethod,
            result: ApiResult<ShippingMethod>,
        ) {
            when (result) {
                // The session has ended; nothing on this screen matters any more.
                ApiResult.Unauthorized -> Unit
                is ApiResult.Forbidden -> {
                    put(before)
                    _uiState.update { it.copy(notice = ShippingNotice.OwnerOnly) }
                    load()
                }
                else -> {
                    put(before)
                    _uiState.update { it.copy(notice = noticeFor(result)) }
                }
            }
        }

        private fun confirmAdd() {
            val state = _uiState.value
            val type = state.adding?.takeIf { state.isOwner && !state.isAdding } ?: return

            _uiState.update { it.copy(isAdding = true) }
            viewModelScope.launch {
                val result = methods.add(ShippingMethodInput(type = type, feeType = ShippingFeeType.Manual))
                _uiState.update { it.copy(isAdding = false) }
                when (result) {
                    is ApiResult.Success -> {
                        put(result.data)
                        _uiState.update { it.copy(adding = null) }
                    }
                    is ApiResult.Forbidden -> {
                        _uiState.update { it.copy(adding = null, notice = ShippingNotice.OwnerOnly) }
                        load()
                    }
                    ApiResult.Unauthorized -> Unit
                    else -> _uiState.update { it.copy(notice = noticeFor(result)) }
                }
            }
        }

        /** Shows [method] in its kind's row. */
        private fun put(method: ShippingMethod) {
            _uiState.update { state ->
                state.copy(rows = state.rows.map { if (it.type == method.type) it.copy(method = method) else it })
            }
        }

        private fun noticeFor(result: ApiResult<*>): ShippingNotice =
            when (result) {
                ApiResult.NetworkError -> ShippingNotice.NoInternet
                is ApiResult.Invalid ->
                    (
                        result.fieldErrors.values
                            .firstOrNull()
                            ?.firstOrNull() ?: result.message
                    )?.let { ShippingNotice.Refused(it) }
                        ?: ShippingNotice.Server
                else -> ShippingNotice.Server
            }
    }
