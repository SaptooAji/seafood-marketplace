package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.ui.test.assert
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsSelected
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performScrollToIndex
import androidx.compose.ui.test.performScrollToNode
import androidx.compose.ui.test.performTextInput
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.product
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.variant
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The product list as the board VD-Produk-Daftar draws it, driven by state only. */
@RunWith(RobolectricTestRunner::class)
class ProductListScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<ProductListEvent>()
    private var addClicks = 0
    private val opened = mutableListOf<Long>()

    private fun show(state: ProductListUiState) {
        compose.setContent {
            SeafoodTheme {
                ProductListScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onAddProduct = { addClicks++ },
                    onOpenProduct = { opened += it },
                )
            }
        }
    }

    private fun loaded(
        products: List<Product>,
        endReached: Boolean = true,
    ) = ProductListUiState(storeName = "UD Kepiting Pak Budi", products = products, isLoading = false, endReached = endReached)

    @Test
    fun aRowShowsTheNameCategoryStartingPriceAndStatus() {
        show(
            loaded(
                listOf(
                    product(
                        id = 1,
                        name = "Kepiting Bakau Super",
                        variants = listOf(variant(id = 1, price = 185_000), variant(id = 2, price = 240_000)),
                    ),
                ),
            ),
        )

        compose.onNodeWithText("UD Kepiting Pak Budi").assertIsDisplayed()
        compose.onNodeWithText("Kepiting Bakau Super").assertIsDisplayed()
        compose.onNodeWithText("Kepiting Bakau").assertIsDisplayed()
        compose.onNodeWithText("Mulai Rp185.000 /kg").assertIsDisplayed()
        // "Aktif" is also a filter chip, so the status is looked for in the row.
        compose.onNodeWithTag(ProductListTags.row(1)).assert(hasText(context.getString(R.string.product_status_active)))
    }

    @Test
    fun rowsMarkStaleStockInactiveAndBlockedProducts() {
        show(
            loaded(
                listOf(
                    product(id = 1, variants = listOf(variant(isStockStale = true))),
                    product(id = 2, isActive = false),
                    product(id = 3, isBlocked = true, blockReason = "<b>tebal</b> foto tidak sesuai"),
                ),
            ),
        )

        compose.onNodeWithText(context.getString(R.string.product_stock_stale)).assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.product_hidden)).assertIsDisplayed()
        // Written by an admin: shown as typed, with no markup turned on.
        val reason = context.getString(R.string.product_blocked_reason, "<b>tebal</b> foto tidak sesuai")
        compose.onNodeWithTag(ProductListTags.LIST).performScrollToNode(hasText(reason))
        compose.onNodeWithText(reason).assertIsDisplayed()
    }

    @Test
    fun aProductWithoutActiveVariantsOrPhotosSaysSo() {
        show(loaded(listOf(product(id = 5, variants = listOf(variant(isActive = false)), images = emptyList()))))

        compose.onNodeWithText(context.getString(R.string.product_no_active_variant)).assertIsDisplayed()
        // The placeholder sits inside the row, whose semantics are merged for the click.
        compose.onNodeWithTag(ProductListTags.noPhoto(5), useUnmergedTree = true).assertIsDisplayed()
    }

    @Test
    fun theWholeListShownSaysSoAtTheEnd() {
        val products = (1L..3L).map { product(id = it) }
        show(loaded(products))

        compose
            .onNodeWithTag(ProductListTags.LIST)
            .performScrollToNode(hasText(context.resources.getQuantityString(R.plurals.product_list_all_shown, 3, 3)))
    }

    @Test
    fun scrollingNearTheEndAsksForMore() {
        show(loaded((1L..20L).map { product(id = it) }, endReached = false))

        compose.onNodeWithTag(ProductListTags.LIST).performScrollToIndex(19)
        compose.waitForIdle()

        assertTrue(events.contains(ProductListEvent.LoadMore))
    }

    @Test
    fun aRowOpensItsProductAndTheButtonAddsOne() {
        show(loaded(listOf(product(id = 42, name = "Rajungan"))))

        compose.onNodeWithText("Rajungan").performClick()
        compose.onNodeWithTag(ProductListTags.ADD).performClick()

        assertEquals(listOf(42L), opened)
        assertEquals(1, addClicks)
    }

    @Test
    fun theFiltersAndTheSearchSendEvents() {
        show(loaded(listOf(product())))

        compose.onNodeWithTag(ProductListTags.filter(ProductStatusFilter.All)).assertIsSelected()
        compose.onNodeWithTag(ProductListTags.filter(ProductStatusFilter.Blocked)).performClick()
        compose.onNodeWithTag(ProductListTags.SEARCH_BUTTON).performClick()
        compose.onNodeWithTag(ProductListTags.SEARCH_FIELD).performTextInput("kepiting")

        assertTrue(events.contains(ProductListEvent.FilterChanged(ProductStatusFilter.Blocked)))
        assertTrue(events.contains(ProductListEvent.QueryChanged("kepiting")))
    }

    @Test
    fun aStoreWithoutProductsIsInvitedToAddTheFirst() {
        show(ProductListUiState(isLoading = false))

        compose.onNodeWithText(context.getString(R.string.product_list_empty_title)).assertIsDisplayed()
        compose.onNodeWithTag(ProductListTags.ADD_FIRST).performClick()

        assertEquals(1, addClicks)
    }

    @Test
    fun aSearchWithoutResultsNamesTheWordAndCanBeCleared() {
        show(ProductListUiState(isLoading = false, query = "kerapu"))

        compose.onNodeWithText(context.getString(R.string.product_list_no_results, "kerapu")).assertIsDisplayed()
        compose.onNodeWithTag(ProductListTags.CLEAR_SEARCH_RESULTS).performClick()

        assertEquals(ProductListEvent.QueryChanged(""), events.last())
    }

    @Test
    fun noConnectionOnTheFirstPageOffersToTryAgain() {
        show(ProductListUiState(isLoading = false, error = UiText.Resource(R.string.error_network)))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        // Robolectric's screen is small: the button is below the fold.
        compose.onNodeWithTag(ProductListTags.RETRY).performScrollTo().performClick()

        assertEquals(ProductListEvent.Retry, events.last())
    }

    @Test
    fun aFailedNextPageKeepsTheListAndOffersToTryAgain() {
        show(loaded(listOf(product()), endReached = false).copy(loadMoreError = UiText.Resource(R.string.product_list_more_failed)))

        compose.onNodeWithTag(ProductListTags.LIST).performScrollToNode(hasText(context.getString(R.string.product_list_more_failed)))
        compose.onNodeWithTag(ProductListTags.RETRY_MORE).performClick()

        assertEquals(ProductListEvent.RetryLoadMore, events.last())
    }
}
