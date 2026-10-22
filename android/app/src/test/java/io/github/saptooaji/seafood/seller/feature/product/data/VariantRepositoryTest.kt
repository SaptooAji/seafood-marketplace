package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantChanges
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonObject
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import mockwebserver3.RecordedRequest
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal

/** Adding, changing, and deleting a product's variants through `seller/products/{id}/variants` and `seller/variants/{id}`. */
class VariantRepositoryTest {
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

    private fun repository(): ProductRepositoryImpl {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), null)
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

    private fun RecordedRequest.bodyJson(): JsonObject = Json.parseToJsonElement(body!!.utf8()).jsonObject

    private val changes =
        VariantChanges(name = "1 kg", unit = VariantUnit.Kg, price = 125_000, minQty = BigDecimal("0.5"), isActive = true)

    @Test
    fun `adding posts the variant with its opening stock and reads it back`() {
        server.enqueue(json(201, """{"data":$VARIANT_JSON}"""))
        val variant = NewVariant("1 kg", VariantUnit.Kg, 120_000, BigDecimal("0.5"), BigDecimal("10"))

        val result = io { repository().addVariant(7, variant) }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/products/7/variants", request.url.encodedPath)
        assertEquals(
            Json.parseToJsonElement("""{"name":"1 kg","unit":"kg","price":120000,"min_qty":"0.5","initial_stock":"10"}"""),
            request.bodyJson(),
        )
        val added = (result as ApiResult.Success).data
        assertEquals(31L, added.id)
        assertEquals(BigDecimal("10.000"), added.stock)
        assertEquals(BigDecimal("0.500"), added.minQty)
    }

    @Test
    fun `adding without an opening stock leaves the field out`() {
        server.enqueue(json(201, """{"data":$VARIANT_JSON}"""))

        io { repository().addVariant(7, NewVariant("1 kg", VariantUnit.Kg, 120_000, BigDecimal("1"), initialStock = null)) }

        assertFalse("initial_stock" in server.takeRequest().bodyJson())
    }

    @Test
    fun `changing puts the variant's details and never its stock`() {
        // Stock only changes through the stock book, so neither field may be sent.
        server.enqueue(json(200, """{"data":$VARIANT_JSON}"""))

        io { repository().updateVariant(31, changes.copy(isActive = false)) }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/api/v1/seller/variants/31", request.url.encodedPath)
        val body = request.bodyJson()
        assertEquals(
            Json.parseToJsonElement("""{"name":"1 kg","unit":"kg","price":125000,"min_qty":"0.5","is_active":false}"""),
            body,
        )
        assertFalse("stock" in body)
        assertFalse("initial_stock" in body)
    }

    @Test
    fun `an active variant says so in the PUT, because the server takes a missing flag as active`() {
        server.enqueue(json(200, """{"data":$VARIANT_JSON}"""))

        io { repository().updateVariant(31, changes) }

        assertEquals("true", server.takeRequest().bodyJson()["is_active"].toString())
    }

    @Test
    fun `deleting deletes the variant`() {
        server.enqueue(MockResponse.Builder().code(204).build())

        val result = io { repository().deleteVariant(31) }

        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/api/v1/seller/variants/31", request.url.encodedPath)
        assertEquals(ApiResult.Success(Unit), result)
    }

    @Test
    fun `the last variant refused by the server comes back with the server's message`() {
        server.enqueue(
            json(
                422,
                """
                {"message":"Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual.",
                 "errors":{"variant":["Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual."]}}
                """.trimIndent(),
            ),
        )

        val result = io { repository().deleteVariant(31) } as ApiResult.Invalid

        assertEquals(listOf("Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual."), result.fieldErrors["variant"])
    }

    @Test
    fun `a variant deleted elsewhere, or of another store, is not found`() {
        server.enqueue(json(404, """{"message":"Not Found."}"""))

        assertEquals(ApiResult.NotFound, io { repository().updateVariant(99, changes) })
    }

    @Test
    fun `a dozen keeps the server's label`() {
        server.enqueue(json(200, """{"data":${VARIANT_JSON.replace(KG, DOZEN)}}"""))

        val variant = (io { repository().updateVariant(31, changes.copy(unit = VariantUnit.Dozen)) } as ApiResult.Success).data

        assertEquals(VariantUnit.Dozen, variant.unit)
        assertEquals("Lusin", variant.unitLabel)
    }

    private companion object {
        const val KG = """{"value":"kg","label":"Kg"}"""
        const val DOZEN = """{"value":"dozen","label":"Lusin"}"""
        const val VARIANT_JSON =
            """{"id":31,"name":"1 kg","unit":$KG,"price":120000,"min_qty":"0.500","stock":"10.000",""" +
                """"stock_confirmed_at":"2026-10-22T08:00:00+07:00","is_stock_stale":false,"is_active":true}"""
    }
}
