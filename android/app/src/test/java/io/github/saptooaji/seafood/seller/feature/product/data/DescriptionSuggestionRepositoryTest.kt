package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.DescriptionSuggestion
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.int
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal
import java.util.Collections

/** An AI description for the product being written (#240), through `seller/products/description-suggestion`. */
class DescriptionSuggestionRepositoryTest {
    private lateinit var server: MockWebServer

    /** The read timeout each request ran with, by path. */
    private val readTimeouts = Collections.synchronizedList(mutableListOf<Pair<String, Int>>())

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
        val client =
            HttpClients
                .okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), debugLogger = null)
                .newBuilder()
                .addInterceptor { chain ->
                    readTimeouts += chain.request().url.encodedPath to chain.readTimeoutMillis()
                    chain.proceed(chain.request())
                }.build()
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

    /** The form as it is when the seller asks: pre order, with a description already written. */
    private val draft =
        ProductDraft(
            categoryId = 2,
            name = "Kepiting Bakau Super",
            description = "Kepiting bakau jantan.",
            seoDescription = null,
            condition = ProductCondition.Live,
            salesMode = SalesMode.PreOrder,
            dailyQuota = BigDecimal("25"),
            leadTimeDays = 1,
            cutoffTime = "15:00",
            shelfLifeDays = 2,
            storageInstructions = "Simpan di wadah lembap dan sejuk.",
            isActive = true,
            attributes = mapOf(10L to "350", 11L to "Super"),
        )

    @Test
    fun `asking sends the form without variants and reads the suggestion`() {
        server.enqueue(json(200, SUGGESTION_JSON))

        val result = io { repository().suggestDescription(draft) }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/products/description-suggestion", request.url.encodedPath)
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("Kepiting Bakau Super", body["name"]!!.jsonPrimitive.content)
        assertEquals(2, body["category_id"]!!.jsonPrimitive.int)
        assertEquals("live", body["condition"]!!.jsonPrimitive.content)
        assertEquals("pre_order", body["sales_mode"]!!.jsonPrimitive.content)
        assertEquals("25", body["daily_quota"]!!.jsonPrimitive.content)
        assertEquals("350", body["attributes"]!!.jsonObject["10"]!!.jsonPrimitive.content)
        assertEquals("Super", body["attributes"]!!.jsonObject["11"]!!.jsonPrimitive.content)
        assertEquals("Simpan di wadah lembap dan sejuk.", body["storage_instructions"]!!.jsonPrimitive.content)
        assertEquals("Kepiting bakau jantan.", body["description"]!!.jsonPrimitive.content)
        assertFalse("variants" in body)
        assertEquals(
            ApiResult.Success(
                DescriptionSuggestion(
                    description = "Kepiting bakau jantan grade super dari tambak Muara Angke.",
                    seoDescription = "Kepiting bakau jantan grade super 300–400 g.",
                ),
            ),
            result,
        )
    }

    @Test
    fun `an empty description is left out, since it is what is asked for`() {
        server.enqueue(json(200, SUGGESTION_JSON))

        io { repository().suggestDescription(draft.copy(description = "")) }

        val body = Json.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        assertFalse("description" in body)
    }

    @Test
    fun `the request carries the Sanctum token and no other key`() {
        server.enqueue(json(200, SUGGESTION_JSON))

        io { repository().suggestDescription(draft) }

        val request = server.takeRequest()
        assertEquals("Bearer sfm_abc", request.headers["Authorization"])
        val names = request.headers.names().map { it.lowercase() }.toSet()
        assertTrue(ALLOWED_HEADERS.containsAll(names), "unexpected headers: ${names - ALLOWED_HEADERS}")
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertTrue(body.keys.none { "key" in it || "token" in it || "secret" in it }, "body keys: ${body.keys}")
    }

    @Test
    fun `the suggestion waits up to 45 seconds for an answer, other requests 30`() {
        server.enqueue(json(200, SUGGESTION_JSON))
        server.enqueue(json(200, """{"data":$PRODUCT_JSON}"""))
        val repository = repository()

        io { repository.suggestDescription(draft) }
        io { repository.update(7, draft) }

        assertEquals(
            listOf(
                "/api/v1/seller/products/description-suggestion" to 45_000,
                "/api/v1/seller/products/7" to 30_000,
            ),
            readTimeouts.toList(),
        )
    }

    @Test
    fun `too many requests keeps the wait from Retry-After`() {
        server.enqueue(
            MockResponse
                .Builder()
                .code(429)
                .addHeader("Content-Type", "application/json")
                .addHeader("Retry-After", "60")
                .body("""{"message":"Terlalu sering meminta saran deskripsi. Tunggu sebentar lalu coba lagi."}""")
                .build(),
        )

        assertEquals(ApiResult.TooManyRequests(60), io { repository().suggestDescription(draft) })
    }

    @Test
    fun `a 503 keeps the server's message`() {
        val message = "Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti atau tulis deskripsi sendiri."
        server.enqueue(json(503, """{"message":"$message"}"""))

        assertEquals(ApiResult.ServiceUnavailable(message), io { repository().suggestDescription(draft) })
    }

    @Test
    fun `a 422 keeps the field errors`() {
        server.enqueue(json(422, """{"message":"Kuota harian wajib diisi.","errors":{"daily_quota":["Kuota harian wajib diisi."]}}"""))

        assertEquals(
            ApiResult.Invalid(mapOf("daily_quota" to listOf("Kuota harian wajib diisi.")), "Kuota harian wajib diisi."),
            io { repository().suggestDescription(draft) },
        )
    }

    private companion object {
        const val SUGGESTION_JSON =
            """{"data":{"description":"Kepiting bakau jantan grade super dari tambak Muara Angke.","seo_description":"Kepiting bakau jantan grade super 300–400 g."}}"""

        /** What OkHttp and the app's own interceptors add; anything else would be a leak. */
        val ALLOWED_HEADERS =
            setOf("authorization", "accept", "content-type", "content-length", "host", "connection", "accept-encoding", "user-agent")

        const val PRODUCT_JSON = """
            {"id":7,"name":"Kepiting Bakau Super","description":"Kepiting bakau jantan.",
             "condition":{"value":"live","label":"Hidup"},"sales_mode":{"value":"pre_order","label":"Pre order"},
             "is_active":true,"blocked":false,"category":{"id":2,"name":"Bakau"},"variants":[]}
        """
    }
}
