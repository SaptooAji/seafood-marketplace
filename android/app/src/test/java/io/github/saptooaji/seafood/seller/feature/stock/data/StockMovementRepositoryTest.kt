package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal
import java.time.Instant
import java.time.LocalDate

/** The stock book through `seller/stock-movements` (#244), as `StockMovementResource` answers. */
class StockMovementRepositoryTest {
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
    fun `the book reads a page of signed movements`() {
        server.enqueue(json(200, PAGE_JSON))

        val page = (io { repository.movements(StockFilter(), page = 1) } as ApiResult.Success).data

        val request = server.takeRequest()
        assertEquals("/api/v1/seller/stock-movements", request.url.encodedPath)
        assertEquals("1", request.url.queryParameter("page"))
        assertNull(request.url.queryParameter("type"))
        assertEquals(2, page.lastPage)
        assertEquals(31, page.total)
        assertEquals(
            StockMovement(
                id = 40,
                type = StockMovementType.StockIn,
                typeLabel = "Stok Masuk",
                qty = BigDecimal("5.000"),
                stockAfter = BigDecimal("12.500"),
                variantId = 11,
                variantName = "300–400 g",
                unitLabel = "Kg",
                productName = "Kepiting Bakau",
                supplierName = "Pak Darto",
                unitCost = 140_000,
                note = "<i>pagi</i>",
                createdBy = "Budi",
                createdAt = Instant.parse("2026-10-17T22:10:00Z"),
            ),
            page.movements[0],
        )
        val system = page.movements[1]
        assertEquals(BigDecimal("-2.000"), system.qty)
        assertNull(system.supplierName)
        assertNull(system.createdBy)
        // A type added on the server later still shows with its label.
        assertNull(system.type)
        assertEquals("Retur", system.typeLabel)
    }

    @Test
    fun `a filter goes into the query`() {
        server.enqueue(json(200, PAGE_JSON))

        io {
            repository.movements(
                StockFilter(
                    variantId = 11,
                    type = StockMovementType.SaleOffline,
                    from = LocalDate.of(2026, 10, 1),
                    to = LocalDate.of(2026, 10, 7),
                ),
                page = 1,
            )
        }

        val url = server.takeRequest().url
        assertEquals("11", url.queryParameter("variant_id"))
        assertEquals("sale_offline", url.queryParameter("type"))
        assertEquals("2026-10-01", url.queryParameter("from"))
        assertEquals("2026-10-07", url.queryParameter("to"))
    }

    @Test
    fun `stock in sends the supplier and the cost`() {
        server.enqueue(json(201, """{"data":$ROW_JSON}"""))

        val result =
            io {
                repository.record(
                    NewStockMovement(
                        variantId = 11,
                        type = StockMovementType.StockIn,
                        qty = BigDecimal("5"),
                        supplierName = "Pak Darto",
                        unitCost = 40_000,
                    ),
                )
            }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/stock-movements", request.url.encodedPath)
        assertEquals(
            """{"variant_id":11,"type":"stock_in","qty":"5","supplier_name":"Pak Darto","unit_cost":40000}""",
            request.body!!.utf8(),
        )
        assertEquals(40L, (result as ApiResult.Success).data.id)
    }

    @Test
    fun `shrinkage sends only the quantity as a plain decimal`() {
        server.enqueue(json(201, """{"data":$ROW_JSON}"""))

        io { repository.record(NewStockMovement(variantId = 11, type = StockMovementType.Shrinkage, qty = BigDecimal("0.500"))) }

        assertEquals("""{"variant_id":11,"type":"shrinkage","qty":"0.5"}""", server.takeRequest().body!!.utf8())
    }

    @Test
    fun `an adjustment may go down`() {
        server.enqueue(json(201, """{"data":$ROW_JSON}"""))

        io {
            repository.record(
                NewStockMovement(variantId = 11, type = StockMovementType.Adjustment, qty = BigDecimal("-2"), note = "Salah hitung"),
            )
        }

        assertEquals(
            """{"variant_id":11,"type":"adjustment","qty":"-2","note":"Salah hitung"}""",
            server.takeRequest().body!!.utf8(),
        )
    }

    @Test
    fun `too little stock comes back on the quantity`() {
        server.enqueue(json(422, """{"message":"Stok tidak cukup.","errors":{"qty":["Stok tidak cukup: sekarang 12 kg."]}}"""))

        val result = io { repository.record(NewStockMovement(11, StockMovementType.Shrinkage, BigDecimal("15"))) }

        assertEquals(mapOf("qty" to listOf("Stok tidak cukup: sekarang 12 kg.")), (result as ApiResult.Invalid).fieldErrors)
    }

    private companion object {
        const val ROW_JSON =
            """{"id":40,"type":{"value":"stock_in","label":"Stok Masuk"},"qty":"5.000","stock_after":"12.500",""" +
                """"variant":{"id":11,"name":"300–400 g","unit":{"value":"kg","label":"Kg"}},""" +
                """"product":{"id":7,"name":"Kepiting Bakau"},"supplier_name":"Pak Darto","unit_cost":140000,""" +
                """"note":"<i>pagi</i>","created_by":{"id":2,"name":"Budi"},"created_at":"2026-10-17T22:10:00+00:00"}"""

        const val SYSTEM_JSON =
            """{"id":39,"type":{"value":"return","label":"Retur"},"qty":"-2.000","stock_after":"7.500",""" +
                """"variant":{"id":11,"name":"300–400 g","unit":{"value":"kg","label":"Kg"}},""" +
                """"product":{"id":7,"name":"Kepiting Bakau"},"supplier_name":null,"unit_cost":null,""" +
                """"note":null,"created_by":null,"created_at":"2026-10-17T20:00:00+00:00"}"""

        const val PAGE_JSON =
            """{"data":[$ROW_JSON,$SYSTEM_JSON],"links":{"first":null,"last":null,"prev":null,"next":null},""" +
                """"meta":{"current_page":1,"from":1,"last_page":2,"path":"x","per_page":30,"to":30,"total":31}}"""
    }
}
