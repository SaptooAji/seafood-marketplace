package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementPage
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.time.Clock
import java.time.LocalDate
import javax.inject.Inject

/**
 * The stock book (#244): pages of 30 loaded as the seller scrolls, a filter
 * kept across a rotation, and a sheet to record stock in, offline sales,
 * shrinkage, and adjustments. A new filter or a recorded change starts again
 * from page 1, dropping the page in flight.
 */
@HiltViewModel
class StockBookViewModel
    @Inject
    constructor(
        private val stock: StockRepository,
        private val clock: Clock,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState =
            MutableStateFlow(
                StockBookUiState(filter = restoredFilter(), today = LocalDate.now(clock), zone = clock.zone),
            )
        val uiState: StateFlow<StockBookUiState> = _uiState.asStateFlow()

        private var loadJob: Job? = null
        private var loadedPage = 0

        private val filters = FilterEditor(_uiState, apply = ::applyFilter)
        private val recorder = StockRecorder(stock, _uiState, viewModelScope, onSaved = ::recorded)

        init {
            loadProducts()
            reload()
        }

        fun onEvent(event: StockBookEvent) {
            when (event) {
                StockBookEvent.LoadMore -> loadMore()
                StockBookEvent.Retry -> reload()
                StockBookEvent.RetryLoadMore -> {
                    _uiState.update { it.copy(loadMoreError = null) }
                    loadMore()
                }
                StockBookEvent.NoticeShown -> _uiState.update { it.copy(notice = null) }
                else -> if (!filters.handle(event)) recorder.handle(event)
            }
        }

        /** The variants to filter by and record for, with their stock now. */
        private fun loadProducts() {
            viewModelScope.launch {
                val result = stock.today()
                if (result is ApiResult.Success) _uiState.update { it.copy(products = result.data) }
            }
        }

        private fun reload() {
            loadJob?.cancel()
            _uiState.update {
                it.copy(
                    isLoading = true,
                    error = null,
                    loadMoreError = null,
                    isLoadingMore = false,
                    endReached = false,
                    movements = emptyList(),
                )
            }
            val filter = _uiState.value.filter
            loadJob =
                viewModelScope.launch {
                    when (val result = stock.movements(filter, page = 1)) {
                        is ApiResult.Success -> {
                            loadedPage = 1
                            _uiState.update { it.copy(isLoading = false).withPage(result.data, replace = true) }
                        }
                        else -> _uiState.update { it.copy(isLoading = false, error = loadMessage(result)) }
                    }
                }
        }

        private fun loadMore() {
            val state = _uiState.value
            if (!state.canLoadMore) return
            _uiState.update { it.copy(isLoadingMore = true) }
            val next = loadedPage + 1
            loadJob =
                viewModelScope.launch {
                    when (val result = stock.movements(state.filter, next)) {
                        is ApiResult.Success -> {
                            loadedPage = next
                            _uiState.update { it.copy(isLoadingMore = false).withPage(result.data, replace = false) }
                        }
                        else ->
                            _uiState.update {
                                it.copy(isLoadingMore = false, loadMoreError = UiText.Resource(R.string.stock_book_more_failed))
                            }
                    }
                }
        }

        private fun applyFilter(filter: StockFilter) {
            savedStateHandle[KEY_VARIANT] = filter.variantId
            savedStateHandle[KEY_TYPE] = filter.type?.value
            savedStateHandle[KEY_FROM] = filter.from?.toString()
            savedStateHandle[KEY_TO] = filter.to?.toString()
            _uiState.update { it.copy(filter = filter) }
            reload()
        }

        /** A change was recorded: the book and the stock are read again, and Stok hari ini hears of it. */
        private fun recorded() {
            _uiState.update { it.copy(notice = UiText.Resource(R.string.stock_record_saved), recorded = it.recorded + 1) }
            loadProducts()
            reload()
        }

        private fun restoredFilter() =
            StockFilter(
                variantId = savedStateHandle.get<Long>(KEY_VARIANT),
                type = savedStateHandle.get<String>(KEY_TYPE)?.let(StockMovementType::from),
                from = savedStateHandle.get<String>(KEY_FROM)?.let(LocalDate::parse),
                to = savedStateHandle.get<String>(KEY_TO)?.let(LocalDate::parse),
            )

        private companion object {
            const val KEY_VARIANT = "stock_book_variant"
            const val KEY_TYPE = "stock_book_type"
            const val KEY_FROM = "stock_book_from"
            const val KEY_TO = "stock_book_to"
        }
    }

/** A loaded page: the first replaces the list, later ones add to it. */
private fun StockBookUiState.withPage(
    page: StockMovementPage,
    replace: Boolean,
) = copy(
    movements = if (replace) page.movements else movements + page.movements,
    endReached = page.currentPage >= page.lastPage,
)
