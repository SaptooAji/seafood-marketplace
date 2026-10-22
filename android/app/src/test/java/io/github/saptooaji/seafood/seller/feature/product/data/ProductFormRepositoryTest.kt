package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.int
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.logging.HttpLoggingInterceptor
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal
import java.util.Collections

/** Creating, reading, updating, and deleting a product through `seller/products`. */
class ProductFormRepositoryTest {
    private lateinit var server: MockWebServer

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
    }

    @AfterEach
    fun stop() {
        server.close()
    }

    private fun repository(logger: HttpLoggingInterceptor.Logger? = null): ProductRepositoryImpl {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), logger)
        return ProductRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(ProductApi::class.java), HttpClients.json)
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

    private fun sentBody(): JsonObject = Json.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject

    private val readyStock =
        ProductDraft(
            categoryId = 2,
            name = "Kepiting Bakau Super",
            description = "Kepiting bakau jantan.",
            seoDescription = null,
            condition = ProductCondition.Fresh,
            salesMode = SalesMode.ReadyStock,
            dailyQuota = null,
            leadTimeDays = null,
            cutoffTime = null,
            shelfLifeDays = 2,
            storageInstructions = null,
            isActive = true,
            attributes = mapOf(10L to "350"),
        )

    private val variant = NewVariant("300–400 g per ekor", VariantUnit.Kg, 185_000, BigDecimal("1"), BigDecimal("12.5"))

    @Test
    fun `creating sends the product, its attributes by id, and the first variant`() {
        server.enqueue(json(201, """{"data":$PRODUCT_JSON}"""))

        io { repository().create(readyStock, variant) }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/products", request.url.encodedPath)
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals(2, body["category_id"]!!.jsonPrimitive.int)
        assertEquals("fresh", body["condition"]!!.jsonPrimitive.content)
        assertEquals("ready_stock", body["sales_mode"]!!.jsonPrimitive.content)
        assertEquals("350", body["attributes"]!!.jsonObject["10"]!!.jsonPrimitive.content)
        assertTrue(body["is_active"]!!.jsonPrimitive.content.toBoolean())
        // A ready-stock product sends no pre-order fields.
        assertFalse("daily_quota" in body)
        assertFalse("lead_time_days" in body)
        assertFalse("cutoff_time" in body)
        val first = body["variants"]!!.jsonArray.single().jsonObject
        assertEquals("300–400 g per ekor", first["name"]!!.jsonPrimitive.content)
        assertEquals("kg", first["unit"]!!.jsonPrimitive.content)
        assertEquals(185_000, first["price"]!!.jsonPrimitive.int)
        assertEquals("1", first["min_qty"]!!.jsonPrimitive.content)
        assertEquals("12.5", first["initial_stock"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a pre-order product sends its quota, lead time, and cutoff`() {
        server.enqueue(json(201, """{"data":$PRODUCT_JSON}"""))
        val preOrder =
            readyStock.copy(
                salesMode = SalesMode.PreOrder,
                dailyQuota = BigDecimal("25"),
                leadTimeDays = 1,
                cutoffTime = "15:00",
            )

        io { repository().create(preOrder, variant) }

        val body = sentBody()
        assertEquals("pre_order", body["sales_mode"]!!.jsonPrimitive.content)
        assertEquals("25", body["daily_quota"]!!.jsonPrimitive.content)
        assertEquals(1, body["lead_time_days"]!!.jsonPrimitive.int)
        assertEquals("15:00", body["cutoff_time"]!!.jsonPrimitive.content)
    }

    @Test
    fun `updating puts the product without variants`() {
        server.enqueue(json(200, """{"data":$PRODUCT_JSON}"""))

        io { repository().update(7, readyStock) }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/api/v1/seller/products/7", request.url.encodedPath)
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertFalse("variants" in body)
        assertEquals("Kepiting Bakau Super", body["name"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a product is read into the form's draft`() {
        server.enqueue(json(200, """{"data":$PRODUCT_JSON}"""))

        val detail = (io { repository().get(7) } as ApiResult.Success).data

        assertEquals("/api/v1/seller/products/7", server.takeRequest().url.encodedPath)
        assertEquals(7L, detail.id)
        with(detail.draft) {
            assertEquals(2L, categoryId)
            assertEquals(ProductCondition.Live, condition)
            assertEquals(SalesMode.PreOrder, salesMode)
            assertEquals(BigDecimal("25.000"), dailyQuota)
            assertEquals(1, leadTimeDays)
            assertEquals("15:00", cutoffTime)
            assertEquals(2, shelfLifeDays)
            assertEquals(mapOf(10L to "350", 11L to "Super"), attributes)
            assertEquals("<script>alert(1)</script> Segar.", description)
        }
        assertTrue(detail.isBlocked)
        assertEquals("Foto tidak sesuai produk.", detail.blockReason)
        assertEquals(listOf(185_000L), detail.variants.map { it.price })
    }

    @Test
    fun `a product's photos are read in their order`() {
        val images =
            """[{"id":3,"url":"http://localhost/3.webp","thumbnail_url":"http://localhost/3_t.webp","sort_order":0},""" +
                """{"id":1,"url":"http://localhost/1.webp","thumbnail_url":"http://localhost/1_t.webp","sort_order":1}]"""
        server.enqueue(json(200, """{"data":${PRODUCT_JSON.replace(""""images":[]""", """"images":$images""")}}"""))

        val detail = (io { repository().get(7) } as ApiResult.Success).data

        assertEquals(listOf(3L, 1L), detail.images.map { it.id })
        assertEquals("http://localhost/3_t.webp", detail.images.first().thumbnailUrl)
    }

    @Test
    fun `a product of another store is not found`() {
        server.enqueue(json(404, """{"message":"Not Found."}"""))

        assertEquals(ApiResult.NotFound, io { repository().get(99) })
    }

    @Test
    fun `deleting deletes the product`() {
        server.enqueue(MockResponse.Builder().code(204).build())

        val result = io { repository().delete(7) }

        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/api/v1/seller/products/7", request.url.encodedPath)
        assertEquals(ApiResult.Success(Unit), result)
    }

    @Test
    fun `refused fields come back keyed as the server sends them`() {
        server.enqueue(
            json(
                422,
                """
                {"message":"Pilih subkategori yang paling spesifik.",
                 "errors":{"category_id":["Pilih subkategori yang paling spesifik."]}}
                """.trimIndent(),
            ),
        )

        val result = io { repository().create(readyStock, variant) }

        assertEquals(mapOf("category_id" to listOf("Pilih subkategori yang paling spesifik.")), (result as ApiResult.Invalid).fieldErrors)
    }

    @Test
    fun `an empty optional text is sent as null`() {
        server.enqueue(json(201, """{"data":$PRODUCT_JSON}"""))

        io { repository().create(readyStock.copy(seoDescription = null, storageInstructions = null), variant) }

        val body = sentBody()
        assertNull(body["seo_description"])
        assertNull(body["storage_instructions"])
    }

    @Test
    fun `the debug log is kept for debug builds only`() {
        // MOB-4: release builds install no logger; debug builds log the product as typed.
        val lines = Collections.synchronizedList(mutableListOf<String>())
        server.enqueue(json(201, """{"data":$PRODUCT_JSON}"""))

        io { repository(HttpLoggingInterceptor.Logger { lines += it }).create(readyStock, variant) }

        assertTrue(lines.joinToString("\n").contains("seller/products"))
    }

    private companion object {
        const val PRODUCT_JSON =
            """{"id":7,"name":"Kepiting Bakau Super","slug":"kepiting-bakau-super","description":"<script>alert(1)</script> Segar.",""" +
                """"seo_description":"Kepiting bakau jantan.","condition":{"value":"live","label":"Hidup"},""" +
                """"sales_mode":{"value":"pre_order","label":"Pre Order"},"daily_quota":"25.000","lead_time_days":1,""" +
                """"cutoff_time":"15:00","shelf_life_days":2,"storage_instructions":"Simpan dingin.","is_active":true,""" +
                """"blocked":true,"block_reason":"Foto tidak sesuai produk.","category":{"id":2,"name":"Bakau"},""" +
                """"variants":[{"id":11,"name":"Super","unit":{"value":"kg","label":"Kg"},"price":185000,"min_qty":"1.000",""" +
                """"stock":"12.000","stock_confirmed_at":null,"is_stock_stale":false,"is_active":true}],"images":[],""" +
                """"attributes":[{"id":10,"name":"Berat per ekor","type":{"value":"number","label":"Angka"},"options":null,""" +
                """"unit":"g","is_required":true,"value":"350"},{"id":11,"name":"Grade","type":{"value":"select","label":"Pilihan"},""" +
                """"options":["Super"],"unit":null,"is_required":false,"value":"Super"},{"id":12,"name":"Asal","type":""" +
                """{"value":"text","label":"Teks"},"options":null,"unit":null,"is_required":false,"value":null}],""" +
                """"created_at":"2026-10-19T08:00:00+07:00","updated_at":"2026-10-19T08:00:00+07:00"}"""
    }
}
