package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.math.BigDecimal
import java.time.Clock
import java.time.ZonedDateTime
import javax.inject.Inject

/**
 * "Stok hari ini" (#243): every active variant, counted with − and + or
 * typed, then one save that sets the changed counts and confirms the rest
 * as they are. Unsaved counts are kept in the [SavedStateHandle], so a
 * rotation or the app being stopped does not lose them. Only ids from the
 * server's list are ever sent.
 */
@HiltViewModel
class StockTodayViewModel
    @Inject
    constructor(
        private val stock: StockRepository,
        private val clock: Clock,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(StockTodayUiState(now = ZonedDateTime.now(clock)))
        val uiState: StateFlow<StockTodayUiState> = _uiState.asStateFlow()

        private var loadJob: Job? = null

        init {
            load()
        }

        fun onEvent(event: StockTodayEvent) {
            when (event) {
                is StockTodayEvent.Increase -> step(event.variantId, BigDecimal.ONE)
                is StockTodayEvent.Decrease -> step(event.variantId, BigDecimal.ONE.negate())
                is StockTodayEvent.Typed -> edit(event.variantId, event.text)
                is StockTodayEvent.Revert -> edit(event.variantId, null)
                StockTodayEvent.Save -> save()
                StockTodayEvent.Retry -> load()
                StockTodayEvent.NoticeShown -> _uiState.update { it.copy(notice = null) }
            }
        }

        private fun load() {
            loadJob?.cancel()
            _uiState.update { it.copy(isLoading = true, loadError = null) }
            loadJob =
                viewModelScope.launch {
                    when (val result = stock.today()) {
                        is ApiResult.Success -> {
                            val ids = result.data.flatMap { product -> product.variants.map { it.id } }.toSet()
                            val counts = restoredCounts().filterKeys { it in ids }
                            _uiState.update {
                                it.copy(isLoading = false, products = result.data, typed = counts, now = ZonedDateTime.now(clock))
                            }
                        }
                        else -> _uiState.update { it.copy(isLoading = false, loadError = loadMessage(result)) }
                    }
                }
        }

        /** − or +: a whole step from the count on screen, never below zero. */
        private fun step(
            id: Long,
            direction: BigDecimal,
        ) {
            val state = _uiState.value
            val variant = state.variant(id) ?: return
            val current = state.counted(id) ?: return
            val next = (current + stepFor(variant.unit) * direction).max(BigDecimal.ZERO)
            edit(id, fieldText(next))
        }

        /** A new count for [id] as typed, or null for the server's again. */
        private fun edit(
            id: Long,
            text: String?,
        ) {
            if (_uiState.value.variant(id) == null) return
            _uiState.update {
                it.copy(
                    typed = if (text == null) it.typed - id else it.typed + (id to text),
                    serverErrors = it.serverErrors - id,
                )
            }
            storeCounts()
        }

        private fun save() {
            val state = _uiState.value
            if (!state.canSave) return
            val counts = state.changed.map { StockCount(it.id, requireNotNull(state.counted(it.id))) }
            _uiState.update { it.copy(isSaving = true, saveError = null) }
            viewModelScope.launch {
                when (val result = stock.confirm(counts)) {
                    is ApiResult.Success -> saved(result.data)
                    is ApiResult.Invalid -> _uiState.update { it.refusedBy(result, counts) }
                    else -> _uiState.update { it.copy(isSaving = false, saveError = saveMessage(result)) }
                }
            }
        }

        /** The server's screen after the save, with nothing left to save. */
        private fun saved(products: List<StockProduct>) {
            val now = ZonedDateTime.now(clock)
            val confirmed = products.sumOf { it.variants.size }
            _uiState.update {
                it.copy(
                    isSaving = false,
                    products = products,
                    typed = emptyMap(),
                    serverErrors = emptyMap(),
                    now = now,
                    notice = UiText.Resource(R.string.stock_confirmed, listOf(confirmed, clockTime(now))),
                )
            }
            storeCounts()
        }

        private fun storeCounts() {
            val typed = _uiState.value.typed
            savedStateHandle[KEY_IDS] = typed.keys.toLongArray()
            savedStateHandle[KEY_COUNTS] = typed.values.toTypedArray()
        }

        private fun restoredCounts(): Map<Long, String> {
            val ids = savedStateHandle.get<LongArray>(KEY_IDS)
            val counts = savedStateHandle.get<Array<String>>(KEY_COUNTS)
            return if (ids == null || counts == null) emptyMap() else ids.zip(counts).toMap()
        }

        private companion object {
            const val KEY_IDS = "stock_typed_ids"
            const val KEY_COUNTS = "stock_typed_counts"
        }
    }
