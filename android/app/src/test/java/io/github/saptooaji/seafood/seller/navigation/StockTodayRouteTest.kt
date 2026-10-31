package io.github.saptooaji.seafood.seller.navigation

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test

/** The stock screen has a fixed route, so the stock reminder (Tahap 6) can open it (#243). */
class StockTodayRouteTest {
    @Test
    fun `the route is stock today`() {
        assertEquals("stock/today", StockTodayRoute.serializer().descriptor.serialName)
    }
}
