package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal

/** `ProductRepositoryImpl` against a MockWebServer speaking the real API's JSON. */
class ProductRepositoryTest {
    private lateinit var server: MockWebServer
    private lateinit var repository: ProductRepositoryImpl

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
        val baseUrl = server.url("/api/v1/")
        val tokenStore = FakeTokenStore().apply { state.value = "sfm_abc" }
        val client = HttpClients.okHttpClient(baseUrl, tokenStore, SessionEvents(), debugLogger = null)
        repository = ProductRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(ProductApi::class.java), HttpClients.json)
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
    fun `the first page of every product sends only the page`() {
        server.enqueue(json(200, PAGE_JSON))

        io { repository.list(ProductStatusFilter.All, search = null, page = 1) }

        val url = server.takeRequest().url
        assertEquals("/api/v1/seller/products", url.encodedPath)
        assertEquals("1", url.queryParameter("page"))
        assertNull(url.queryParameter("status"))
        assertNull(url.queryParameter("search"))
    }

    @Test
    fun `the status and the search are sent, the search encoded`() {
        server.enqueue(json(200, PAGE_JSON))

        io { repository.list(ProductStatusFilter.Blocked, search = "kepiting & cumi", page = 2) }

        val url = server.takeRequest().url
        assertEquals("blocked", url.queryParameter("status"))
        assertEquals("kepiting & cumi", url.queryParameter("search"))
        assertEquals("2", url.queryParameter("page"))
        assertTrue(url.encodedQuery!!.contains("search=kepiting%20%26%20cumi"))
    }

    @Test
    fun `a page is read with its products, variants, photos, and paging`() {
        server.enqueue(json(200, PAGE_JSON))

        val page = (io { repository.list(ProductStatusFilter.All, null, 1) } as ApiResult.Success).data

        assertEquals(1, page.currentPage)
        assertEquals(2, page.lastPage)
        assertEquals(21, page.total)
        val product = page.products.single()
        assertEquals(7L, product.id)
        assertEquals("Kepiting Bakau Super", product.name)
        assertEquals("Kepiting Bakau", product.categoryName)
        assertTrue(product.isActive)
        assertTrue(product.isBlocked)
        assertEquals("<b>tebal</b> foto tidak sesuai", product.blockReason)
        assertEquals(
            listOf(
                ProductVariant(11, "Super 500 g", "Kg", 185_000, BigDecimal("18.500"), isStockStale = true, isActive = true),
                ProductVariant(12, "Jumbo", "Kg", 240_000, BigDecimal("0.000"), isStockStale = false, isActive = false),
            ),
            product.variants,
        )
        assertEquals(
            listOf(
                ProductImage(3, "http://localhost:8000/storage/products/7/a.webp", "http://localhost:8000/storage/products/7/a_thumb.webp"),
            ),
            product.images,
        )
    }

    @Test
    fun `no connection is a network error`() {
        server.close()

        assertEquals(ApiResult.NetworkError, io { repository.list(ProductStatusFilter.All, null, 1) })
    }

    private companion object {
        /** `ProductResource::collection` paginated: `data`, `links`, and `meta`. */
        const val PAGE_JSON =
            """{"data":[{"id":7,"name":"Kepiting Bakau Super","slug":"kepiting-bakau-super","description":"Segar.",""" +
                """"seo_description":null,"condition":{"value":"live","label":"Hidup"},""" +
                """"sales_mode":{"value":"ready_stock","label":"Ready stok"},""" +
                """"daily_quota":null,"lead_time_days":null,"cutoff_time":null,"shelf_life_days":2,"storage_instructions":null,""" +
                """"is_active":true,"blocked":true,"block_reason":"<b>tebal</b> foto tidak sesuai",""" +
                """"category":{"id":4,"name":"Kepiting Bakau"},""" +
                """"variants":[{"id":11,"name":"Super 500 g","unit":{"value":"kg","label":"Kg"},"price":185000,"min_qty":"1.000",""" +
                """"stock":"18.500","stock_confirmed_at":null,"is_stock_stale":true,"is_active":true},""" +
                """{"id":12,"name":"Jumbo","unit":{"value":"kg","label":"Kg"},"price":240000,"min_qty":"1.000",""" +
                """"stock":"0.000","stock_confirmed_at":null,"is_stock_stale":false,"is_active":false}],""" +
                """"images":[{"id":3,"url":"http://localhost:8000/storage/products/7/a.webp",""" +
                """"thumbnail_url":"http://localhost:8000/storage/products/7/a_thumb.webp","sort_order":1}],""" +
                """"created_at":"2026-10-19T08:00:00+07:00","updated_at":"2026-10-19T08:00:00+07:00"}],""" +
                """"links":{"first":"http://localhost:8000/api/v1/seller/products?page=1",""" +
                """"last":"http://localhost:8000/api/v1/seller/products?page=2",""" +
                """"prev":null,"next":"http://localhost:8000/api/v1/seller/products?page=2"},""" +
                """"meta":{"current_page":1,"from":1,"last_page":2,""" +
                """"path":"http://localhost:8000/api/v1/seller/products","per_page":20,"to":1,"total":21}}"""
    }
}
