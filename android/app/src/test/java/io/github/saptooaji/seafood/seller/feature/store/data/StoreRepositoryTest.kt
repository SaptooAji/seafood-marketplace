package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonNull
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

/** `StoreRepositoryImpl` against a MockWebServer speaking the real API's JSON. */
class StoreRepositoryTest {
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

    private fun repository(debugLogger: HttpLoggingInterceptor.Logger? = null): StoreRepositoryImpl {
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger)
        return StoreRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(StoreApi::class.java), HttpClients.json)
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

    private val details =
        StoreDetails(
            name = "UD Kepiting Pak Budi",
            description = null,
            phone = "081234567890",
            address = "Jl. Dermaga Ujung No. 12, Muara Angke",
            city = "Jakarta Utara",
            lat = BigDecimal("-6.1067000"),
            lng = BigDecimal("106.7740000"),
            bankName = "BCA",
            bankAccountNo = "1234567890",
            bankAccountName = "Budi Santoso",
        )

    @Test
    fun `opening a store posts every field with coordinates of seven decimals`() {
        server.enqueue(json(201, """{"data":$PENDING_STORE_JSON}"""))

        io { repository().open(details) }

        val request = server.takeRequest()
        assertEquals("POST", request.method)
        assertEquals("/api/v1/seller/store", request.url.encodedPath)
        assertEquals("Bearer sfm_abc", request.headers["Authorization"])
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("UD Kepiting Pak Budi", body["name"]!!.jsonPrimitive.content)
        assertEquals("081234567890", body["phone"]!!.jsonPrimitive.content)
        assertEquals("Jl. Dermaga Ujung No. 12, Muara Angke", body["address"]!!.jsonPrimitive.content)
        assertEquals("Jakarta Utara", body["city"]!!.jsonPrimitive.content)
        assertEquals("-6.1067000", body["lat"]!!.jsonPrimitive.content)
        assertEquals("106.7740000", body["lng"]!!.jsonPrimitive.content)
        assertEquals("BCA", body["bank_name"]!!.jsonPrimitive.content)
        assertEquals("1234567890", body["bank_account_no"]!!.jsonPrimitive.content)
        assertEquals("Budi Santoso", body["bank_account_name"]!!.jsonPrimitive.content)
        // An empty description goes as null, never as "".
        assertTrue(body["description"] == null || body["description"] == JsonNull)
    }

    @Test
    fun `a new store is read from the store resource`() {
        server.enqueue(json(201, """{"data":$PENDING_STORE_JSON}"""))

        val result = io { repository().open(details) }

        assertEquals(ApiResult.Success(FakeStoreRepository.PENDING_STORE), result)
    }

    @Test
    fun `a rejected store keeps its reason, and a store seen by staff has no bank`() {
        server.enqueue(
            json(
                201,
                """{"data":{"id":3,"name":"Toko","slug":"toko","description":"Segar","phone":"081234567890",""" +
                    """"address":"Jl. A","city":"Jakarta Utara","lat":"-6.1067000","lng":"106.7740000",""" +
                    """"status":{"value":"rejected","label":"Ditolak"},"verified_at":null,""" +
                    """"rejection_reason":"Foto KTP buram.","stock_reminder_time":null}}""",
            ),
        )

        val store = (io { repository().open(details) } as ApiResult.Success).data

        assertEquals(StoreStatus.Rejected, store.status)
        assertEquals("Ditolak", store.statusLabel)
        assertEquals("Foto KTP buram.", store.rejectionReason)
        assertEquals("Segar", store.description)
        assertNull(store.bank)
    }

    @Test
    fun `a status the app does not know yet is read as unknown`() {
        server.enqueue(json(201, """{"data":${PENDING_STORE_JSON.replace("\"pending\"", "\"archived\"")}}"""))

        val store = (io { repository().open(details) } as ApiResult.Success).data

        assertEquals(StoreStatus.Unknown, store.status)
    }

    @Test
    fun `a seller who already owns a store gets a conflict`() {
        server.enqueue(json(409, """{"message":"Anda sudah memiliki toko."}"""))

        val result = io { repository().open(details) }

        assertEquals(ApiResult.Conflict("Anda sudah memiliki toko."), result)
    }

    @Test
    fun `refused fields come back keyed as the server sends them`() {
        server.enqueue(
            json(
                422,
                """{"message":"$ACCOUNT_REFUSED","errors":{"bank_account_no":["$ACCOUNT_REFUSED"]}}""",
            ),
        )

        val result = io { repository().open(details) }

        assertEquals(ApiResult.Invalid(mapOf("bank_account_no" to listOf(ACCOUNT_REFUSED)), ACCOUNT_REFUSED), result)
    }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the debug log masks the bank account number both ways`() {
        val lines = Collections.synchronizedList(mutableListOf<String>())
        server.enqueue(json(201, """{"data":$PENDING_STORE_JSON}"""))

        io { repository(debugLogger = HttpLoggingInterceptor.Logger { lines += it }).open(details) }

        val log = lines.joinToString("\n")
        assertTrue(log.contains("seller/store"))
        assertFalse(log.contains("1234567890"))
    }

    @Test
    fun `store details and a bank account never print the account number`() {
        assertFalse(details.toString().contains("1234567890"))
        assertFalse(BankAccount("BCA", "1234567890", "Budi Santoso").toString().contains("1234567890"))
        assertFalse(FakeStoreRepository.PENDING_STORE.toString().contains("1234567890"))
    }
}

/** `StoreResource` for the owner of a new store: pending, with the bank account. */
private const val PENDING_STORE_JSON =
    """{"id":3,"name":"UD Kepiting Pak Budi","slug":"ud-kepiting-pak-budi","description":null,""" +
        """"phone":"081234567890","address":"Jl. Dermaga Ujung No. 12, Muara Angke","city":"Jakarta Utara",""" +
        """"lat":"-6.1067000","lng":"106.7740000","status":{"value":"pending","label":"Menunggu Persetujuan"},""" +
        """"verified_at":null,"rejection_reason":null,"stock_reminder_time":null,""" +
        """"bank":{"name":"BCA","account_no":"1234567890","account_name":"Budi Santoso"}}"""

// The server's message under APP_LOCALE=en; the app shows its own text instead.
private const val ACCOUNT_REFUSED = "The bank account no field format is invalid."
