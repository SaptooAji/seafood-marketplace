package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter

data class ProductListUiState(
    val storeName: String? = null,
    /** As typed; the request uses it trimmed, 400 ms after the last key. */
    val query: String = "",
    val filter: ProductStatusFilter = ProductStatusFilter.All,
    val products: List<Product> = emptyList(),
    /** The first page is loading (not a refresh, which keeps the list on screen). */
    val isLoading: Boolean = true,
    val isRefreshing: Boolean = false,
    /** Why the first page failed; the list is replaced by a screen to try again. */
    val error: UiText? = null,
    val isLoadingMore: Boolean = false,
    /** Why the next page failed; shown below the pages already loaded. */
    val loadMoreError: UiText? = null,
    val endReached: Boolean = false,
) {
    val content: ProductListContent
        get() =
            when {
                error != null -> ProductListContent.Error
                isLoading -> ProductListContent.Loading
                products.isNotEmpty() -> ProductListContent.Products
                query.isBlank() && filter == ProductStatusFilter.All -> ProductListContent.NoProducts
                else -> ProductListContent.NoResults
            }
}

enum class ProductListContent { Loading, Products, NoProducts, NoResults, Error }

sealed interface ProductListEvent {
    data class QueryChanged(
        val query: String,
    ) : ProductListEvent

    data class FilterChanged(
        val filter: ProductStatusFilter,
    ) : ProductListEvent

    /** The list is scrolled near its end. */
    data object LoadMore : ProductListEvent

    /** Try the first page again after it failed. */
    data object Retry : ProductListEvent

    /** Try the next page again after it failed. */
    data object RetryLoadMore : ProductListEvent

    /** Pull to refresh. */
    data object Refresh : ProductListEvent
}

/** Test tags of the product list. */
object ProductListTags {
    const val LIST = "product_list"
    const val SEARCH_BUTTON = "product_search_button"
    const val SEARCH_FIELD = "product_search_field"
    const val CLEAR_SEARCH = "product_search_clear"
    const val CLEAR_SEARCH_RESULTS = "product_search_clear_results"
    const val ADD = "product_add"
    const val ADD_FIRST = "product_add_first"
    const val RETRY = "product_retry"
    const val RETRY_MORE = "product_retry_more"

    fun filter(filter: ProductStatusFilter) = "product_filter_${filter.name}"

    fun row(id: Long) = "product_row_$id"

    fun noPhoto(id: Long) = "product_no_photo_$id"
}
