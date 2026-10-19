package io.github.saptooaji.seafood.seller.feature.product.domain

import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.product
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.variant
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

/** What a product row shows, worked out from the API's product. */
class ProductTest {
    @Test
    fun `the starting price is the cheapest active variant and ignores inactive ones`() {
        val p =
            product(
                variants =
                    listOf(
                        variant(id = 1, price = 185_000),
                        variant(id = 2, price = 45_000, isActive = false),
                        variant(id = 3, price = 120_000, unitLabel = "Pack"),
                    ),
            )

        assertEquals(PriceFrom(price = 120_000, unitLabel = "Pack", activeVariants = 2), p.priceFrom)
    }

    @Test
    fun `a product without active variants has no starting price`() {
        assertNull(product(variants = listOf(variant(isActive = false))).priceFrom)
        assertNull(product(variants = emptyList()).priceFrom)
    }

    @Test
    fun `a blocked product is blocked whatever its own switch says`() {
        assertEquals(ProductStatus.Blocked, product(isActive = true, isBlocked = true).status)
        assertEquals(ProductStatus.Blocked, product(isActive = false, isBlocked = true).status)
        assertEquals(ProductStatus.Inactive, product(isActive = false).status)
        assertEquals(ProductStatus.Active, product().status)
    }

    @Test
    fun `stale stock on any variant marks the product`() {
        assertTrue(product(variants = listOf(variant(id = 1), variant(id = 2, isStockStale = true))).hasStaleStock)
        assertFalse(product().hasStaleStock)
    }

    @Test
    fun `the thumbnail is the first photo's, or none`() {
        assertEquals("http://localhost:8000/storage/p/7_thumb.webp", product(id = 7).thumbnailUrl)
        assertNull(product(images = emptyList()).thumbnailUrl)
    }

    @Test
    fun `filters send the status the API knows`() {
        assertNull(ProductStatusFilter.All.query)
        assertEquals("active", ProductStatusFilter.Active.query)
        assertEquals("inactive", ProductStatusFilter.Inactive.query)
        assertEquals("blocked", ProductStatusFilter.Blocked.query)
    }
}
