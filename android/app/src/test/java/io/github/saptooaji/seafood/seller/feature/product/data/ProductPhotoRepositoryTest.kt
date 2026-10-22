package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.long
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.io.TempDir
import java.io.File
import java.util.Collections

/** The photos of a saved product (#241): `seller/products/{id}/images`. */
class ProductPhotoRepositoryTest {
    private lateinit var server: MockWebServer

    @TempDir
    lateinit var dir: File

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

    private fun repository(): ProductPhotoRepositoryImpl {
        val baseUrl = server.url("/api/v1/")
        val client =
            HttpClients
                .okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), debugLogger = null)
                .newBuilder()
                .addInterceptor { chain ->
                    readTimeouts += chain.request().url.encodedPath to chain.readTimeoutMillis()
                    chain.proceed(chain.request())
                }.build()
        return ProductPhotoRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(ProductApi::class.java), HttpClients.json)
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

    /** A prepared photo as the cache holds it, under a name the server must never see. */
    private fun photo(bytes: Int = 64 * 1024): File = File(dir, "IMG_20261022_rumah_saya.jpg").apply { writeBytes(ByteArray(bytes) { 7 }) }

    @Test
    fun `uploading sends one multipart photo named photo jpg and reads the new image`() {
        server.enqueue(json(201, """{"data":${image(31)}}"""))

        val result = io { repository().upload(7, photo()) {} }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/products/7/images", request.url.encodedPath)
        assertTrue(request.headers["Content-Type"]!!.startsWith("multipart/form-data"))
        val body = request.body!!.utf8()
        assertTrue("""name="photo"; filename="photo.jpg"""" in body, body.take(300))
        assertTrue("Content-Type: image/jpeg" in body, body.take(300))
        assertTrue("rumah" !in body)
        assertEquals(ApiResult.Success(productImage(31)), result)
    }

    @Test
    fun `progress grows to the whole file`() {
        server.enqueue(json(201, """{"data":${image(31)}}"""))
        val progress = Collections.synchronizedList(mutableListOf<Float>())

        io { repository().upload(7, photo(bytes = 512 * 1024)) { progress += it } }

        assertTrue(progress.isNotEmpty())
        assertEquals(progress.sorted(), progress.toList())
        assertEquals(1f, progress.last())
    }

    @Test
    fun `an upload waits up to 60 seconds for an answer`() {
        server.enqueue(json(201, """{"data":${image(31)}}"""))

        io { repository().upload(7, photo()) {} }

        assertEquals(listOf("/api/v1/seller/products/7/images" to 60_000), readTimeouts.toList())
    }

    @Test
    fun `a refused photo keeps the server's message`() {
        server.enqueue(json(422, """{"message":"Produk paling banyak memiliki 8 foto.","errors":{"photo":["Produk paling banyak memiliki 8 foto."]}}"""))

        assertEquals(
            ApiResult.Invalid(mapOf("photo" to listOf("Produk paling banyak memiliki 8 foto.")), "Produk paling banyak memiliki 8 foto."),
            io { repository().upload(7, photo()) {} },
        )
    }

    @Test
    fun `too many uploads keep the wait and the message`() {
        server.enqueue(
            MockResponse
                .Builder()
                .code(429)
                .addHeader("Content-Type", "application/json")
                .addHeader("Retry-After", "42")
                .body("""{"message":"Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi."}""")
                .build(),
        )

        assertEquals(
            ApiResult.TooManyRequests(42, "Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi."),
            io { repository().upload(7, photo()) {} },
        )
    }

    @Test
    fun `reordering sends every id in the new order and reads the photos back`() {
        server.enqueue(json(200, """{"data":[${image(3)},${image(1)},${image(2)}]}"""))

        val result = io { repository().reorder(7, listOf(3, 1, 2)) }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/api/v1/seller/products/7/images/order", request.url.encodedPath)
        val ids = Json.parseToJsonElement(request.body!!.utf8()).jsonObject["image_ids"]!!.jsonArray.map { it.jsonPrimitive.long }
        assertEquals(listOf(3L, 1L, 2L), ids)
        assertEquals(ApiResult.Success(listOf(productImage(3), productImage(1), productImage(2))), result)
    }

    @Test
    fun `deleting removes one photo of the product`() {
        server.enqueue(MockResponse.Builder().code(204).build())

        val result = io { repository().delete(7, 2) }

        val request = server.takeRequest()
        assertEquals("DELETE", request.method)
        assertEquals("/api/v1/seller/products/7/images/2", request.url.encodedPath)
        assertEquals(ApiResult.Success(Unit), result)
    }

    @Test
    fun `another store's product is not found`() {
        server.enqueue(json(404, """{"message":"Not Found"}"""))

        assertEquals(ApiResult.NotFound, io { repository().delete(99, 2) })
    }

    private companion object {
        fun image(id: Long) =
            """{"id":$id,"url":"http://localhost:8000/storage/p/$id.webp",""" +
                """"thumbnail_url":"http://localhost:8000/storage/p/${id}_thumb.webp","sort_order":$id}"""

        fun productImage(id: Long) =
            ProductImage(id, "http://localhost:8000/storage/p/$id.webp", "http://localhost:8000/storage/p/${id}_thumb.webp")
    }
}
