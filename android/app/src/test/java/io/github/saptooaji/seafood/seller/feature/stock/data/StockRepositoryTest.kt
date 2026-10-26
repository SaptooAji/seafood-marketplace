package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal
import java.time.Instant

/** `StockRepositoryImpl` against a MockWebServer speaking `DailyStockResource`'s JSON (#243). */
class StockRepositoryTest {
    private lateinit var server: MockWebServer
    private lateinit var repository: StockRepositoryImpl

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), null)
        repository = StockRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(StockApi::class.java), HttpClients.json)
    }

    @AfterEach
    fun stop() {
        server.close()
    }

    private fun json(
        code: Int,
        body: String,
    ) = MockResponse
        .Builder()
        .code(code)
        .addHeader("Content-Type", "application/json")
        .body(body)
        .build()

    private fun <T> io(block: suspend () -> T): T = runBlocking { withContext(Dispatchers.IO) { block() } }

    @Test
    fun `today reads the variants grouped by product`() {
        server.enqueue(json(200, SCREEN_JSON))

        val products = (io { repository.today() } as ApiResult.Success).data

        val request = server.takeRequest()
        assertEquals("GET", request.method)
        assertEquals("/api/v1/seller/stock/today", request.url.encodedPath)
        assertEquals(listOf("Cumi Segar", "Kakap Merah"), products.map { it.name })
        assertEquals(
            StockVariant(
                id = 11,
                name = "Sedang",
                unit = VariantUnit.Kg,
                unitLabel = "Kg",
                stock = BigDecimal("10.000"),
                confirmedAt = Instant.parse("2026-10-17T23:40:00Z"),
                isStale = false,
            ),
            products[0].variants[0],
        )
        val never = products[1].variants[0]
        assertEquals(VariantUnit.Piece, never.unit)
        assertEquals(null, never.confirmedAt)
        assertEquals(true, never.isStale)
    }

    @Test
    fun `no change confirms everything without a variants list`() {
        server.enqueue(json(200, SCREEN_JSON))

        val result = io { repository.confirm(emptyList()) }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/stock/confirm", request.url.encodedPath)
        assertEquals("""{"confirm_all":true}""", request.body!!.utf8())
        assertEquals(2, (result as ApiResult.Success).data.size)
    }

    @Test
    fun `a changed count is sent as a plain decimal with the rest confirmed`() {
        server.enqueue(json(200, SCREEN_JSON))

        io { repository.confirm(listOf(StockCount(11, BigDecimal("7.500")))) }

        assertEquals("""{"variants":[{"id":11,"stock":"7.5"}],"confirm_all":true}""", server.takeRequest().body!!.utf8())
    }

    @Test
    fun `a whole count has no decimals and zero is sent as 0`() {
        server.enqueue(json(200, SCREEN_JSON))

        io { repository.confirm(listOf(StockCount(11, BigDecimal("12.000")), StockCount(12, BigDecimal("0.000")))) }

        assertEquals(
            """{"variants":[{"id":11,"stock":"12"},{"id":12,"stock":"0"}],"confirm_all":true}""",
            server.takeRequest().body!!.utf8(),
        )
    }

    @Test
    fun `a refused count keeps the server's field key`() {
        server.enqueue(
            json(
                422,
                """{"message":"Stok tidak valid.","errors":{"variants.0.stock":["The variants.0.stock field must be at least 0."]}}""",
            ),
        )

        val result = io { repository.confirm(listOf(StockCount(11, BigDecimal("1")))) }

        assertEquals(
            mapOf("variants.0.stock" to listOf("The variants.0.stock field must be at least 0.")),
            (result as ApiResult.Invalid).fieldErrors,
        )
    }

    private companion object {
        const val SCREEN_JSON =
            """{"data":[{"id":4,"name":"Cumi Segar","variants":[{"id":11,"name":"Sedang","unit":{"value":"kg","label":"Kg"},""" +
                """"stock":"10.000","stock_confirmed_at":"2026-10-17T23:40:00+00:00","is_stock_stale":false}]},""" +
                """{"id":5,"name":"Kakap Merah","variants":[{"id":12,"name":"Utuh 1 kg","unit":{"value":"piece","label":"Pcs"},""" +
                """"stock":"3.000","stock_confirmed_at":null,"is_stock_stale":true}]}]}"""
    }
}
