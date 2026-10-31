package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
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
import java.time.LocalTime
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
            bankAccountNo = "7700112233",
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
        assertEquals("7700112233", body["bank_account_no"]!!.jsonPrimitive.content)
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

    // --- The current store -------------------------------------------------

    @Test
    fun `the current store is read from GET seller store`() {
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        val result = io { repository().current() }

        assertEquals(ApiResult.Success(CurrentStore.Exists(FakeStoreRepository.PENDING_STORE)), result)
        val request = server.takeRequest()
        assertEquals("GET", request.method)
        assertEquals("/api/v1/seller/store", request.url.encodedPath)
    }

    @Test
    fun `a seller without a store gets no store, not an error`() {
        server.enqueue(json(404, """{"message":"Not Found."}"""))

        val result = io { repository().current() }

        assertEquals(ApiResult.Success(CurrentStore.NoStore), result)
    }

    @Test
    fun `other failures of the current store pass through`() {
        server.enqueue(json(500, """{"message":"Server Error"}"""))

        val result = io { repository().current() }

        assertEquals(ApiResult.ServerError, result)
    }

    @Test
    fun `the role of the user asking and the reminder time are read from the store`() {
        server.enqueue(json(200, """{"data":$STAFF_STORE_JSON}"""))

        val store = ((io { repository().current() } as ApiResult.Success).data as CurrentStore.Exists).store

        assertEquals(StoreRole.Staff, store.role)
        assertEquals(LocalTime.of(6, 30), store.stockReminderTime)
        assertNull(store.bank)
    }

    @Test
    fun `the owner's role is read with the bank account`() {
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        val store = ((io { repository().current() } as ApiResult.Success).data as CurrentStore.Exists).store

        assertEquals(StoreRole.Owner, store.role)
        assertNull(store.stockReminderTime)
        assertEquals("7700112233", store.bank!!.accountNo)
    }

    @Test
    fun `a role the app does not know yet is read as unknown`() {
        server.enqueue(json(200, """{"data":${STAFF_STORE_JSON.replace("\"staff\"", "\"cashier\"")}}"""))

        val store = ((io { repository().current() } as ApiResult.Success).data as CurrentStore.Exists).store

        assertEquals(StoreRole.Unknown, store.role)
    }

    // --- Editing the store ------------------------------------------------

    @Test
    fun `editing a store puts every field with the reminder time`() {
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        io { repository().update(details.copy(name = "UD Kepiting Bu Sari", stockReminderTime = LocalTime.of(6, 30))) }

        val request = server.takeRequest()
        assertEquals("PUT", request.method)
        assertEquals("/api/v1/seller/store", request.url.encodedPath)
        assertEquals("Bearer sfm_abc", request.headers["Authorization"])
        val body = Json.parseToJsonElement(request.body!!.utf8()).jsonObject
        assertEquals("UD Kepiting Bu Sari", body["name"]!!.jsonPrimitive.content)
        assertEquals("081234567890", body["phone"]!!.jsonPrimitive.content)
        assertEquals("Jl. Dermaga Ujung No. 12, Muara Angke", body["address"]!!.jsonPrimitive.content)
        assertEquals("Jakarta Utara", body["city"]!!.jsonPrimitive.content)
        assertEquals("-6.1067000", body["lat"]!!.jsonPrimitive.content)
        assertEquals("106.7740000", body["lng"]!!.jsonPrimitive.content)
        assertEquals("BCA", body["bank_name"]!!.jsonPrimitive.content)
        assertEquals("7700112233", body["bank_account_no"]!!.jsonPrimitive.content)
        assertEquals("Budi Santoso", body["bank_account_name"]!!.jsonPrimitive.content)
        assertEquals("06:30", body["stock_reminder_time"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a reminder time before ten in the morning keeps its leading zero`() {
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        io { repository().update(details.copy(stockReminderTime = LocalTime.of(5, 5))) }

        val body = Json.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        assertEquals("05:05", body["stock_reminder_time"]!!.jsonPrimitive.content)
    }

    @Test
    fun `a removed reminder is sent as null, not left out`() {
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        io { repository().update(details.copy(stockReminderTime = null)) }

        val body = Json.parseToJsonElement(server.takeRequest().body!!.utf8()).jsonObject
        // A missing key would leave the old time on the server.
        assertTrue(body.containsKey("stock_reminder_time"))
        assertEquals(JsonNull, body["stock_reminder_time"])
    }

    @Test
    fun `an edited store is read back from the store resource`() {
        server.enqueue(json(200, """{"data":$STAFF_STORE_JSON}"""))

        val result = io { repository().update(details) }

        val store = (result as ApiResult.Success).data
        assertEquals("UD Kepiting Pak Budi", store.name)
        assertEquals(LocalTime.of(6, 30), store.stockReminderTime)
    }

    @Test
    fun `a member who is not the owner gets forbidden with the server's message`() {
        server.enqueue(json(403, """{"message":"This action is unauthorized."}"""))

        val result = io { repository().update(details) }

        assertEquals(ApiResult.Forbidden("This action is unauthorized."), result)
    }

    @Test
    fun `a refused phone on editing comes back keyed as the server sends it`() {
        server.enqueue(json(422, """{"message":"$PHONE_REFUSED","errors":{"phone":["$PHONE_REFUSED"]}}"""))

        val result = io { repository().update(details) }

        assertEquals(ApiResult.Invalid(mapOf("phone" to listOf(PHONE_REFUSED)), PHONE_REFUSED), result)
    }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the debug log masks the bank account number both ways`() {
        val lines = Collections.synchronizedList(mutableListOf<String>())
        server.enqueue(json(201, """{"data":$PENDING_STORE_JSON}"""))

        io { repository(debugLogger = HttpLoggingInterceptor.Logger { lines += it }).open(details) }

        val log = lines.joinToString("\n")
        assertTrue(log.contains("seller/store"))
        assertFalse(log.contains("7700112233"))
    }

    @Test
    fun `the debug log masks the bank account number when editing too`() {
        val lines = Collections.synchronizedList(mutableListOf<String>())
        server.enqueue(json(200, """{"data":$PENDING_STORE_JSON}"""))

        io { repository(debugLogger = HttpLoggingInterceptor.Logger { lines += it }).update(details) }

        val log = lines.joinToString("\n")
        assertTrue(log.contains("PUT"))
        assertTrue(log.contains("bank_account_no"))
        assertFalse(log.contains("7700112233"))
    }

    @Test
    fun `store details and a bank account never print the account number`() {
        assertFalse(details.toString().contains("7700112233"))
        assertFalse(BankAccount("BCA", "7700112233", "Budi Santoso").toString().contains("7700112233"))
        assertFalse(FakeStoreRepository.PENDING_STORE.toString().contains("7700112233"))
    }
}

/** `StoreResource` for the owner of a new store: pending, with the bank account. */
private const val PENDING_STORE_JSON =
    """{"id":3,"name":"UD Kepiting Pak Budi","slug":"ud-kepiting-pak-budi","description":null,""" +
        """"phone":"081234567890","address":"Jl. Dermaga Ujung No. 12, Muara Angke","city":"Jakarta Utara",""" +
        """"lat":"-6.1067000","lng":"106.7740000","status":{"value":"pending","label":"Menunggu Persetujuan"},""" +
        """"verified_at":null,"rejection_reason":null,"stock_reminder_time":null,""" +
        """"role":{"value":"owner","label":"Pemilik"},""" +
        """"bank":{"name":"BCA","account_no":"7700112233","account_name":"Budi Santoso"}}"""

/** `StoreResource` for a staff member of an active store: no bank account, a 06:30 reminder. */
private const val STAFF_STORE_JSON =
    """{"id":3,"name":"UD Kepiting Pak Budi","slug":"ud-kepiting-pak-budi","description":null,""" +
        """"phone":"081234567890","address":"Jl. Dermaga Ujung No. 12, Muara Angke","city":"Jakarta Utara",""" +
        """"lat":"-6.1067000","lng":"106.7740000","status":{"value":"active","label":"Aktif"},""" +
        """"verified_at":"2026-10-20T09:00:00+07:00","rejection_reason":null,"stock_reminder_time":"06:30",""" +
        """"role":{"value":"staff","label":"Staf"}}"""

// The server's message under APP_LOCALE=en; the app shows its own text instead.
private const val ACCOUNT_REFUSED = "The bank account no field format is invalid."
private const val PHONE_REFUSED = "The phone field format is invalid."
