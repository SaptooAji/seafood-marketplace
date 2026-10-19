package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import kotlinx.coroutines.Job
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * The products tab: pages of 20 loaded as the seller scrolls, a search sent
 * 400 ms after the last key, and a status filter. Any new search, filter, or
 * refresh cancels the request in flight, so an old page never lands in a
 * new list.
 */
@HiltViewModel
class ProductListViewModel
    @Inject
    constructor(
        private val products: ProductRepository,
        private val stores: StoreRepository,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(ProductListUiState())
        val uiState: StateFlow<ProductListUiState> = _uiState.asStateFlow()

        private var loadJob: Job? = null
        private var searchJob: Job? = null
        private var loadedPage = 0

        /** The search the current list was asked with: trimmed, null when blank. */
        private var search: String? = null

        init {
            viewModelScope.launch { loadStoreName() }
            reload()
        }

        fun onEvent(event: ProductListEvent) {
            when (event) {
                is ProductListEvent.QueryChanged -> changeQuery(event.query)
                is ProductListEvent.FilterChanged -> {
                    if (event.filter == _uiState.value.filter) return
                    _uiState.update { it.copy(filter = event.filter) }
                    reload()
                }
                ProductListEvent.LoadMore -> loadMore()
                ProductListEvent.RetryLoadMore -> {
                    _uiState.update { it.copy(loadMoreError = null) }
                    loadMore()
                }
                ProductListEvent.Retry -> reload()
                ProductListEvent.Refresh -> reload(refresh = true)
            }
        }

        private suspend fun loadStoreName() {
            val result = stores.current()
            val store = ((result as? ApiResult.Success)?.data as? CurrentStore.Exists)?.store ?: return
            // Only the name is kept; the rest of the store (its bank account) is not needed here.
            _uiState.update { it.copy(storeName = store.name) }
        }

        private fun changeQuery(typed: String) {
            val query = typed.take(MAX_SEARCH_LENGTH)
            _uiState.update { it.copy(query = query) }
            searchJob?.cancel()
            searchJob =
                viewModelScope.launch {
                    delay(SEARCH_DEBOUNCE_MILLIS)
                    val wanted = query.trim().ifEmpty { null }
                    if (wanted != search) {
                        search = wanted
                        reload()
                    }
                }
        }

        /** Starts again from page 1, dropping whatever is loading. */
        private fun reload(refresh: Boolean = false) {
            loadJob?.cancel()
            _uiState.update {
                it.copy(
                    isLoading = !refresh,
                    isRefreshing = refresh,
                    isLoadingMore = false,
                    error = null,
                    loadMoreError = null,
                    endReached = false,
                    products = if (refresh) it.products else emptyList(),
                )
            }
            val filter = _uiState.value.filter
            loadJob =
                viewModelScope.launch {
                    when (val result = products.list(filter, search, page = 1)) {
                        is ApiResult.Success -> {
                            loadedPage = 1
                            _uiState.update { it.copy(isLoading = false, isRefreshing = false).withPage(result.data, replace = true) }
                        }
                        else -> _uiState.update { it.copy(isLoading = false, isRefreshing = false, error = messageFor(result)) }
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
                    when (val result = products.list(state.filter, search, next)) {
                        is ApiResult.Success -> {
                            loadedPage = next
                            _uiState.update { it.copy(isLoadingMore = false).withPage(result.data, replace = false) }
                        }
                        else ->
                            _uiState.update {
                                it.copy(
                                    isLoadingMore = false,
                                    loadMoreError = UiText.Resource(R.string.product_list_more_failed),
                                )
                            }
                    }
                }
        }

        /** Nothing is loading, there is more to load, and nothing failed that needs a retry first. */
        private val ProductListUiState.canLoadMore: Boolean
            get() {
                val busy = isLoading || isRefreshing || isLoadingMore
                val stopped = endReached || loadMoreError != null || error != null
                return !busy && !stopped
            }

        private fun ProductListUiState.withPage(
            page: ProductPage,
            replace: Boolean,
        ) = copy(
            products = if (replace) page.products else products + page.products,
            endReached = page.currentPage >= page.lastPage,
        )

        private fun messageFor(result: ApiResult<*>): UiText =
            UiText.Resource(if (result == ApiResult.NetworkError) R.string.error_network else R.string.error_server)

        private companion object {
            const val SEARCH_DEBOUNCE_MILLIS = 400L

            /** `ProductIndexRequest` takes at most 100 characters. */
            const val MAX_SEARCH_LENGTH = 100
        }
    }
