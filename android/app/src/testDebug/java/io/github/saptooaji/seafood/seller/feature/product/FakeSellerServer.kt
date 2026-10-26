package io.github.saptooaji.seafood.seller.feature.product

import mockwebserver3.Dispatcher
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import mockwebserver3.RecordedRequest
import okhttp3.HttpUrl
import java.io.Closeable

/**
 * The backend as the add-product flow meets it (#242): an active store, the
 * category tree, and a product list that holds what was saved. The JSON
 * follows the API resources (StoreResource, CategoryResource,
 * ProductResource). A request without the test token is refused with 401.
 */
class FakeSellerServer : Closeable {
    private val server = MockWebServer()

    /** The body of the last `POST seller/products`, as sent. */
    @Volatile
    var createdBody: String? = null
        private set

    /** Answers the next product save with 422 on `name`, as the server would. */
    @Volatile
    var refuseName = false

    val baseUrl: HttpUrl

    init {
        server.dispatcher =
            object : Dispatcher() {
                override fun dispatch(request: RecordedRequest): MockResponse = answer(request)
            }
        server.start()
        baseUrl = server.url("/api/v1/")
    }

    private fun answer(request: RecordedRequest): MockResponse {
        if (request.headers["Authorization"] != "Bearer $TOKEN") return json(UNAUTHORIZED, """{"message":"Unauthenticated."}""")
        val path = request.url.encodedPath.removePrefix("/api/v1/")
        return when {
            request.method == "GET" && path == "seller/store" -> json(OK, """{"data":$ACTIVE_STORE}""")
            request.method == "GET" && path == "categories" -> json(OK, CATEGORY_TREE)
            request.method == "GET" && path == "seller/products" -> json(OK, page(if (createdBody == null) "" else CREATED_PRODUCT))
            request.method == "POST" && path == "seller/products" -> save(request)
            else -> json(NOT_FOUND, """{"message":"Not Found."}""")
        }
    }

    private fun save(request: RecordedRequest): MockResponse {
        if (refuseName) return json(INVALID, """{"message":"$NAME_REFUSED","errors":{"name":["$NAME_REFUSED"]}}""")
        createdBody = request.body?.utf8()
        return json(CREATED, """{"data":$CREATED_PRODUCT}""")
    }

    override fun close() = server.close()

    companion object {
        /** A made-up token in the shape the server gives (`sfm_`). */
        const val TOKEN = "sfm_test"

        const val CREATED_ID = 7L
        const val NAME_REFUSED = "The name has already been taken."

        private const val OK = 200
        private const val CREATED = 201
        private const val UNAUTHORIZED = 401
        private const val NOT_FOUND = 404
        private const val INVALID = 422

        private fun json(
            code: Int,
            body: String,
        ) = MockResponse
            .Builder()
            .code(code)
            .addHeader("Content-Type", "application/json")
            .body(body)
            .build()

        private fun page(products: String) =
            """{"data":[$products],"links":{"first":"http://localhost/api/v1/seller/products?page=1",""" +
                """"last":"http://localhost/api/v1/seller/products?page=1","prev":null,"next":null},""" +
                """"meta":{"current_page":1,"from":${if (products.isEmpty()) "null" else "1"},"last_page":1,""" +
                """"path":"http://localhost/api/v1/seller/products","per_page":20,""" +
                """"to":${if (products.isEmpty()) "null" else "1"},"total":${if (products.isEmpty()) 0 else 1}}}"""

        private const val ACTIVE_STORE =
            """{"id":3,"name":"UD Kepiting Pak Budi","slug":"ud-kepiting-pak-budi","description":null,""" +
                """"phone":"081234567890","address":"Jl. Dermaga Ujung No. 12, Muara Angke","city":"Jakarta Utara",""" +
                """"lat":"-6.1067000","lng":"106.7740000","status":{"value":"active","label":"Aktif"},""" +
                """"verified_at":"2026-10-19T08:00:00+07:00","rejection_reason":null,"stock_reminder_time":null,""" +
                """"bank":{"name":"BCA","account_no":"7700112233","account_name":"Budi Santoso"}}"""

        private const val WEIGHT =
            """{"id":10,"name":"Berat per ekor","type":{"value":"number","label":"Angka"},"options":null,"unit":"g",""" +
                """"is_required":true,"is_filterable":false,"inherited":false}"""

        /** Kepiting has one leaf, Bakau, with a required weight. */
        private const val CATEGORY_TREE =
            """{"data":[{"id":1,"name":"Kepiting","slug":"kepiting","sort_order":1,"quality_standard":null,""" +
                """"attributes":[$WEIGHT],"children":[{"id":2,"name":"Bakau","slug":"bakau","sort_order":1,""" +
                """"quality_standard":"- Hidup","attributes":[$WEIGHT],"children":[]}]},""" +
                """{"id":6,"name":"Tiram","slug":"tiram","sort_order":2,"quality_standard":null,"attributes":[],"children":[]}]}"""

        /** What the server answers for the product the flow saves. */
        private const val CREATED_PRODUCT =
            """{"id":$CREATED_ID,"name":"Kepiting Bakau Super","slug":"kepiting-bakau-super",""" +
                """"description":"Kepiting bakau hidup dari tambak Muara Angke.","seo_description":null,""" +
                """"condition":{"value":"live","label":"Hidup"},"sales_mode":{"value":"ready_stock","label":"Ready stok"},""" +
                """"daily_quota":null,"lead_time_days":null,"cutoff_time":null,"shelf_life_days":null,""" +
                """"storage_instructions":null,"is_active":true,"blocked":false,"block_reason":null,""" +
                """"category":{"id":2,"name":"Bakau"},""" +
                """"variants":[{"id":11,"name":"Super 500 g","unit":{"value":"kg","label":"Kg"},"price":185000,""" +
                """"min_qty":"1.000","stock":"18.000","stock_confirmed_at":"2026-10-26T08:00:00+07:00",""" +
                """"is_stock_stale":false,"is_active":true}],"images":[],""" +
                """"created_at":"2026-10-26T08:00:00+07:00","updated_at":"2026-10-26T08:00:00+07:00"}"""
    }
}
