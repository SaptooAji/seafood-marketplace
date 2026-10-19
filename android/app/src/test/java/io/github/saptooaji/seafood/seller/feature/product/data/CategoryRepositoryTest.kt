package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.runBlocking
import kotlinx.coroutines.withContext
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import org.junit.jupiter.api.AfterEach
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.BeforeEach
import org.junit.jupiter.api.Test

/** `CategoryRepositoryImpl` against `GET categories`, kept in memory for the session. */
class CategoryRepositoryTest {
    private lateinit var server: MockWebServer
    private lateinit var repository: CategoryRepositoryImpl

    @BeforeEach
    fun start() {
        server = MockWebServer()
        server.start()
        val baseUrl = server.url("/api/v1/")
        val client = HttpClients.okHttpClient(baseUrl, FakeTokenStore().apply { state.value = "sfm_abc" }, SessionEvents(), debugLogger = null)
        repository = CategoryRepositoryImpl(HttpClients.retrofit(baseUrl, client).create(CategoryApi::class.java), HttpClients.json)
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
    fun `the tree is read with its attributes and children`() {
        server.enqueue(json(200, TREE_JSON))

        val tree = (io { repository.tree() } as ApiResult.Success).data

        assertEquals("/api/v1/categories", server.takeRequest().url.encodedPath)
        val weight = CategoryAttribute(10, "Berat per ekor", AttributeType.Number, emptyList(), "g", isRequired = true)
        val grade = CategoryAttribute(11, "Grade", AttributeType.Select, listOf("Super", "Jantan"), null, isRequired = false)
        assertEquals(
            listOf(
                Category(1, "Kepiting", listOf(weight), listOf(Category(2, "Bakau", listOf(weight, grade), emptyList()))),
                Category(6, "Tiram", emptyList(), emptyList()),
            ),
            tree,
        )
    }

    @Test
    fun `the tree is asked for once a session`() {
        server.enqueue(json(200, TREE_JSON))

        io { repository.tree() }
        io { repository.tree() }

        assertEquals(1, server.requestCount)
    }

    @Test
    fun `a failure is not kept, so the next call asks again`() {
        server.enqueue(json(500, """{"message":"Server Error"}"""))
        server.enqueue(json(200, TREE_JSON))

        assertEquals(ApiResult.ServerError, io { repository.tree() })
        assertEquals(2, (io { repository.tree() } as ApiResult.Success).data.size)
    }

    @Test
    fun `an attribute type this app does not know is read as text`() {
        server.enqueue(json(200, TREE_JSON.replace("\"select\"", "\"color\"")))

        val bakau = (io { repository.tree() } as ApiResult.Success).data[0].children[0]

        assertEquals(AttributeType.Text, bakau.attributes[1].type)
    }

    private companion object {
        const val WEIGHT_JSON =
            """{"id":10,"name":"Berat per ekor","type":{"value":"number","label":"Angka"},"options":null,"unit":"g",""" +
                """"is_required":true,"is_filterable":false,"inherited":false}"""
        const val GRADE_JSON =
            """{"id":11,"name":"Grade","type":{"value":"select","label":"Pilihan"},"options":["Super","Jantan"],"unit":null,""" +
                """"is_required":false,"is_filterable":true,"inherited":false}"""
        const val TREE_JSON =
            """{"data":[{"id":1,"name":"Kepiting","slug":"kepiting","sort_order":1,"quality_standard":null,""" +
                """"attributes":[$WEIGHT_JSON],"children":[{"id":2,"name":"Bakau","slug":"bakau","sort_order":1,""" +
                """"quality_standard":"- Hidup","attributes":[$WEIGHT_JSON,$GRADE_JSON],"children":[]}]},""" +
                """{"id":6,"name":"Tiram","slug":"tiram","sort_order":2,"quality_standard":null,"attributes":[],"children":[]}]}"""
    }
}
