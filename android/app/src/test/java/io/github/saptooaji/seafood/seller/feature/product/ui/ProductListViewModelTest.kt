package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceTimeBy
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension

@OptIn(ExperimentalCoroutinesApi::class)
class ProductListViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val products = FakeProductRepository()
    private val stores = FakeStoreRepository()

    private fun viewModel() = ProductListViewModel(products, stores)

    // --- Loading and paging ---------------------------------------------

    @Test
    fun `the first page loads every product and the store name`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 2)

            val vm = viewModel()
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(20, state.products.size)
            assertEquals("UD Kepiting Pak Budi", state.storeName)
            assertFalse(state.isLoading)
            assertFalse(state.endReached)
            assertEquals(listOf(FakeProductRepository.Call(ProductStatusFilter.All, null, 1)), products.calls)
        }

    @Test
    fun `scrolling to the end loads page 2 and then reaches the end`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 2)
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(ProductListEvent.LoadMore)
            advanceUntilIdle()

            assertEquals(40, vm.uiState.value.products.size)
            assertEquals(
                listOf(1L, 21L),
                listOf(
                    vm.uiState.value.products
                        .first()
                        .id,
                    vm.uiState.value.products[20]
                        .id,
                ),
            )
            assertTrue(vm.uiState.value.endReached)

            vm.onEvent(ProductListEvent.LoadMore)
            advanceUntilIdle()
            assertEquals(2, products.calls.size)
        }

    @Test
    fun `asking for more while a page loads sends one request`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 3)
            val vm = viewModel()
            advanceUntilIdle()
            products.gates[2] = CompletableDeferred()

            vm.onEvent(ProductListEvent.LoadMore)
            runCurrent()
            assertTrue(vm.uiState.value.isLoadingMore)
            vm.onEvent(ProductListEvent.LoadMore)
            runCurrent()

            products.gates[2]!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(listOf(1, 2), products.calls.map { it.page })
        }

    // --- Search and filter ----------------------------------------------

    @Test
    fun `typing fast sends one search 400 ms after the last key`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()
            products.calls.clear()

            for (text in listOf("k", "ke", "kep", "kepiting")) {
                vm.onEvent(ProductListEvent.QueryChanged(text))
                advanceTimeBy(100)
            }
            assertEquals("kepiting", vm.uiState.value.query)
            advanceTimeBy(299)
            runCurrent()
            assertTrue(products.calls.isEmpty())

            advanceTimeBy(2)
            advanceUntilIdle()
            assertEquals(listOf(FakeProductRepository.Call(ProductStatusFilter.All, "kepiting", 1)), products.calls)
        }

    @Test
    fun `the search is trimmed, a blank one sends none, and it stops at 100 characters`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(ProductListEvent.QueryChanged("  cumi  "))
            advanceUntilIdle()
            vm.onEvent(ProductListEvent.QueryChanged("   "))
            advanceUntilIdle()
            vm.onEvent(ProductListEvent.QueryChanged("a".repeat(150)))
            advanceUntilIdle()

            assertEquals(listOf(null, "cumi", null, "a".repeat(100)), products.calls.map { it.search })
            assertEquals(100, vm.uiState.value.query.length)
        }

    @Test
    fun `choosing Diblokir asks for blocked products from page 1`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 3)
            val vm = viewModel()
            advanceUntilIdle()
            vm.onEvent(ProductListEvent.LoadMore)
            advanceUntilIdle()

            vm.onEvent(ProductListEvent.FilterChanged(ProductStatusFilter.Blocked))
            advanceUntilIdle()

            assertEquals(FakeProductRepository.Call(ProductStatusFilter.Blocked, null, 1), products.calls.last())
            assertEquals(ProductStatusFilter.Blocked, vm.uiState.value.filter)
            assertEquals(20, vm.uiState.value.products.size)
        }

    @Test
    fun `changing the filter while page 2 loads throws the old page 2 away`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 3)
            val vm = viewModel()
            advanceUntilIdle()
            products.gates[2] = CompletableDeferred()
            vm.onEvent(ProductListEvent.LoadMore)
            runCurrent()

            products.answers[1] = ApiResult.Success(ProductPage(listOf(product(id = 900)), currentPage = 1, lastPage = 1, total = 1))
            vm.onEvent(ProductListEvent.FilterChanged(ProductStatusFilter.Inactive))
            advanceUntilIdle()
            products.gates[2]!!.complete(Unit)
            advanceUntilIdle()

            assertEquals(
                listOf(900L),
                vm.uiState.value.products
                    .map { it.id },
            )
            assertFalse(vm.uiState.value.isLoadingMore)
            assertTrue(vm.uiState.value.endReached)
        }

    // --- Empty states ---------------------------------------------------

    @Test
    fun `a store without products is told to add the first one`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(ProductListContent.NoProducts, vm.uiState.value.content)
        }

    @Test
    fun `a search without results says so`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(ProductListEvent.QueryChanged("kerapu"))
            advanceUntilIdle()

            assertEquals(ProductListContent.NoResults, vm.uiState.value.content)
        }

    // --- Errors ---------------------------------------------------------

    @Test
    fun `no connection on page 1 shows the error and trying again loads it`() =
        runTest(main.dispatcher) {
            products.answers[1] = ApiResult.NetworkError
            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(ProductListContent.Error, vm.uiState.value.content)
            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.error)

            products.answerPages(lastPage = 1)
            vm.onEvent(ProductListEvent.Retry)
            advanceUntilIdle()

            assertEquals(ProductListContent.Products, vm.uiState.value.content)
            assertNull(vm.uiState.value.error)
        }

    @Test
    fun `no connection on page 2 keeps page 1 and offers to try again below it`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 2)
            val vm = viewModel()
            advanceUntilIdle()
            val page2 = products.answers.getValue(2)
            products.answers[2] = ApiResult.NetworkError

            vm.onEvent(ProductListEvent.LoadMore)
            advanceUntilIdle()

            assertEquals(20, vm.uiState.value.products.size)
            assertEquals(UiText.Resource(R.string.product_list_more_failed), vm.uiState.value.loadMoreError)
            assertEquals(ProductListContent.Products, vm.uiState.value.content)

            products.answers[2] = page2
            vm.onEvent(ProductListEvent.RetryLoadMore)
            advanceUntilIdle()

            assertEquals(40, vm.uiState.value.products.size)
            assertNull(vm.uiState.value.loadMoreError)
        }

    @Test
    fun `a server error on page 1 shows the server message`() =
        runTest(main.dispatcher) {
            products.answers[1] = ApiResult.ServerError

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.error)
        }

    // --- Refresh ----------------------------------------------------------

    @Test
    fun `pulling to refresh reloads page 1 and keeps the list until it arrives`() =
        runTest(main.dispatcher) {
            products.answerPages(lastPage = 2)
            val vm = viewModel()
            advanceUntilIdle()
            vm.onEvent(ProductListEvent.LoadMore)
            advanceUntilIdle()
            products.gates[1] = CompletableDeferred()

            vm.onEvent(ProductListEvent.Refresh)
            runCurrent()
            assertTrue(vm.uiState.value.isRefreshing)
            assertEquals(40, vm.uiState.value.products.size)

            products.gates[1]!!.complete(Unit)
            advanceUntilIdle()
            assertFalse(vm.uiState.value.isRefreshing)
            assertEquals(20, vm.uiState.value.products.size)
            assertEquals(1, products.calls.last().page)
        }
}
