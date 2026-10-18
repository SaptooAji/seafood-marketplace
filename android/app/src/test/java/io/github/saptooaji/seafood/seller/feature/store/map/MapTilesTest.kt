package io.github.saptooaji.seafood.seller.feature.store.map

import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.int
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import mockwebserver3.MockResponse
import mockwebserver3.MockWebServer
import okhttp3.Request
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

/**
 * The OpenStreetMap tile setup (OSM tile usage policy): one tile URL, a
 * User-Agent that names the app, and a plain background when tiles fail.
 */
class MapTilesTest {
    @Test
    fun `the user agent names the app, its version, and where to find it`() {
        assertEquals(
            "SeafoodMarketplaceSeller/0.4.0 (+https://github.com/SaptooAji/seafood-marketplace)",
            MapTiles.userAgent("0.4.0"),
        )
    }

    @Test
    fun `tile requests carry the app's user agent instead of the map library's`() {
        MockWebServer().use { server ->
            server.start()
            server.enqueue(
                MockResponse
                    .Builder()
                    .code(200)
                    .body("png")
                    .build(),
            )

            MapTiles
                .httpClient("0.4.0")
                .newCall(
                    Request
                        .Builder()
                        .url(server.url("/15/26124/16950.png"))
                        // MapLibre adds its own User-Agent to every request.
                        .addHeader("User-Agent", "MapLibre Android/13.6.1")
                        .build(),
                ).execute()
                .close()

            val sent = server.takeRequest().headers.values("User-Agent")
            assertEquals(listOf(MapTiles.userAgent("0.4.0")), sent)
        }
    }

    @Test
    fun `tiles come from the OpenStreetMap tile server`() {
        assertEquals("https://tile.openstreetmap.org/{z}/{x}/{y}.png", MapTiles.TILE_URL)

        val source =
            style()["sources"]!!
                .jsonObject.values
                .single()
                .jsonObject
        assertEquals("raster", source["type"]!!.jsonPrimitive.content)
        assertEquals(listOf(MapTiles.TILE_URL), source["tiles"]!!.jsonArray.map { it.jsonPrimitive.content })
        assertEquals(256, source["tileSize"]!!.jsonPrimitive.int)
        assertEquals(19, source["maxzoom"]!!.jsonPrimitive.int)
        assertTrue(source["attribution"]!!.jsonPrimitive.content.contains("OpenStreetMap"))
    }

    @Test
    fun `the map draws a plain background under the tiles for when they do not load`() {
        val layers = style()["layers"]!!.jsonArray.map { it.jsonObject }

        val background = layers.first()
        assertEquals("background", background["type"]!!.jsonPrimitive.content)
        assertEquals(
            "#ECE8DD",
            background["paint"]!!
                .jsonObject["background-color"]!!
                .jsonPrimitive.content
                .uppercase(),
        )
        assertEquals("raster", layers[1]["type"]!!.jsonPrimitive.content)
    }

    private fun style(): JsonObject = Json.parseToJsonElement(MapTiles.styleJson).jsonObject
}
