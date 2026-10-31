package io.github.saptooaji.seafood.seller.feature.shipping.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.jsonObject
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test
import java.math.BigDecimal

/** `ShippingMethodRepositoryImpl` against a MockWebServer speaking the real API's JSON. */
class ShippingMethodRepositoryTest {
    private lateinit var server: MockWebServer
    private val tokenStore = FakeTokenStore().apply { state.value = "sfm_abc" }
    private val sessionEvents = SessionEvents()

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
    }

    @AfterEach
    fun stop() {
        server.close()
    }

    private fun repository(): ShippingMethodRepositoryImpl {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger = null)
        return ShippingMethodRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(ShippingMethodApi::class.java), HttpClients.json)
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

    private fun expected(body: String): JsonObject = Json.parseToJsonElement(body).jsonObject

    // --- The list ---------------------------------------------------------

    @Test
    fun `the list is read from GET seller shipping-methods`() {
        server.enqueue(json(200, """{"data":[$PICKUP_JSON,$OWN_PER_KM_JSON]}"""))

        val result = io { repository().list() }

        assertEquals(ApiResult.Success(listOf(FakeShippingMethodRepository.PICKUP, FakeShippingMethodRepository.OWN_PER_KM)), result)
        val request = server.takeRequest()
        assertEquals("GET", request.method)
        assertEquals("/api/v1/seller/shipping-methods", request.url.encodedPath)
        assertEquals("Bearer sfm_abc", request.headers["Authorization"])
    }

    @Test
    fun `a radius keeps the decimals the server sends`() {
        server.enqueue(json(200, """{"data":[${OWN_PER_KM_JSON.replace("\"20.00\"", "\"7.50\"")}]}"""))

        val method = (io { repository().list() } as ApiResult.Success).data.single()

        assertEquals(BigDecimal("7.50"), method.maxRadiusKm)
    }

    @Test
    fun `a manual method has no fees`() {
        server.enqueue(json(200, """{"data":[$OJOL_JSON]}"""))

        val method = (io { repository().list() } as ApiResult.Success).data.single()

        assertEquals(FakeShippingMethodRepository.OJOL, method)
    }

    @Test
    fun `a kind the app does not know yet is left out`() {
        server.enqueue(json(200, """{"data":[$PICKUP_JSON,${OJOL_JSON.replace("\"ojol\"", "\"drone\"")}]}"""))

        val methods = (io { repository().list() } as ApiResult.Success).data

        assertEquals(listOf(ShippingType.Pickup), methods.map { it.type })
    }

    // --- Adding -----------------------------------------------------------

    @Test
    fun `adding own delivery with a flat fee posts only the fields that fee uses`() {
        server.enqueue(json(201, """{"data":$OWN_PER_KM_JSON}"""))

        io {
            repository().add(
                ShippingMethodInput(
                    type = ShippingType.OwnDelivery,
                    feeType = ShippingFeeType.Flat,
                    maxRadiusKm = BigDecimal("10"),
                    flatFee = 10_000,
                ),
            )
        }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/shipping-methods", request.url.encodedPath)
        assertEquals(
            expected("""{"type":"own_delivery","fee_type":"flat","max_radius_km":"10","flat_fee":10000}"""),
            Json.parseToJsonElement(request.body!!.utf8()).jsonObject,
        )
    }

    @Test
    fun `adding own delivery per km sends the fee per km and the minimum, without a flat fee`() {
        server.enqueue(json(201, """{"data":$OWN_PER_KM_JSON}"""))

        io {
            repository().add(
                ShippingMethodInput(
                    type = ShippingType.OwnDelivery,
                    feeType = ShippingFeeType.PerKm,
                    maxRadiusKm = BigDecimal("20"),
                    feePerKm = 3_000,
                    minFee = 10_000,
                ),
            )
        }

        assertEquals(
            expected("""{"type":"own_delivery","fee_type":"per_km","max_radius_km":"20","fee_per_km":3000,"min_fee":10000}"""),
            sentBody(),
        )
    }

    @Test
    fun `adding ojol sends the manual fee and nothing else`() {
        server.enqueue(json(201, """{"data":$OJOL_JSON}"""))

        val result = io { repository().add(ShippingMethodInput(type = ShippingType.Ojol, feeType = ShippingFeeType.Manual)) }

        assertEquals(expected("""{"type":"ojol","fee_type":"manual"}"""), sentBody())
        assertEquals(ApiResult.Success(FakeShippingMethodRepository.OJOL), result)
    }

    // --- Updating ---------------------------------------------------------

    @Test
    fun `turning pickup off puts its fields with is_active false and no type`() {
        server.enqueue(json(200, """{"data":${PICKUP_JSON.replace("\"is_active\":true", "\"is_active\":false")}}"""))

        val result =
            io {
                repository().update(
                    1,
                    ShippingMethodInput(type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0, isActive = false),
                )
            }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/api/v1/seller/shipping-methods/1", request.url.encodedPath)
        assertEquals(
            expected("""{"fee_type":"flat","flat_fee":0,"is_active":false}"""),
            Json.parseToJsonElement(request.body!!.utf8()).jsonObject,
        )
        assertEquals(false, (result as ApiResult.Success).data.isActive)
    }

    @Test
    fun `switching own delivery from flat to per km sends no flat fee`() {
        server.enqueue(json(200, """{"data":$OWN_PER_KM_JSON}"""))

        io {
            repository().update(
                2,
                ShippingMethodInput(
                    type = ShippingType.OwnDelivery,
                    feeType = ShippingFeeType.PerKm,
                    maxRadiusKm = BigDecimal("7.5"),
                    feePerKm = 3_000,
                    minFee = 10_000,
                    isActive = true,
                ),
            )
        }

        assertEquals(
            expected("""{"fee_type":"per_km","max_radius_km":"7.5","fee_per_km":3000,"min_fee":10000,"is_active":true}"""),
            sentBody(),
        )
    }

    @Test
    fun `turning off the last active method comes back as a refused is_active`() {
        server.enqueue(json(422, """{"message":"$LAST_ACTIVE","errors":{"is_active":["$LAST_ACTIVE"]}}"""))

        val result =
            io {
                repository().update(
                    1,
                    ShippingMethodInput(type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0, isActive = false),
                )
            }

        assertEquals(ApiResult.Invalid(mapOf("is_active" to listOf(LAST_ACTIVE)), LAST_ACTIVE), result)
    }

    @Test
    fun `a staff member who changes a method gets forbidden`() {
        server.enqueue(json(403, """{"message":"This action is unauthorized."}"""))

        val result =
            io { repository().update(1, ShippingMethodInput(type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0)) }

        assertEquals(ApiResult.Forbidden("This action is unauthorized."), result)
    }

    @Test
    fun `a method of another store is not found`() {
        server.enqueue(json(404, """{"message":"Not Found."}"""))

        val result =
            io { repository().update(99, ShippingMethodInput(type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0)) }

        assertEquals(ApiResult.NotFound, result)
    }
}

private const val PICKUP_JSON =
    """{"id":1,"type":{"value":"pickup","label":"Ambil Sendiri"},"fee_type":{"value":"flat","label":"Tetap"},""" +
        """"max_radius_km":null,"flat_fee":0,"fee_per_km":null,"min_fee":null,"is_active":true}"""

private const val OWN_PER_KM_JSON =
    """{"id":2,"type":{"value":"own_delivery","label":"Pengiriman Sendiri"},"fee_type":{"value":"per_km","label":"Per Kilometer"},""" +
        """"max_radius_km":"20.00","flat_fee":null,"fee_per_km":3000,"min_fee":10000,"is_active":true}"""

private const val OJOL_JSON =
    """{"id":3,"type":{"value":"ojol","label":"Ojek Online"},"fee_type":{"value":"manual","label":"Manual"},""" +
        """"max_radius_km":null,"flat_fee":null,"fee_per_km":null,"min_fee":null,"is_active":true}"""

private const val LAST_ACTIVE = "Toko harus punya minimal satu cara kirim yang aktif."
