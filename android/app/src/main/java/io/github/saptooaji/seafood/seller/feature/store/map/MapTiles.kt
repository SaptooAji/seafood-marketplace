package io.github.saptooaji.seafood.seller.feature.store.map

import okhttp3.OkHttpClient

/**
 * Where map tiles come from, in one place (like `store-map.js` on the web) so
 * they can move to another provider before production. OpenStreetMap needs
 * no key (MOB-2), but its tile usage policy asks for a User-Agent that names
 * the app; tiles are cached by MapLibre's own cache, and nothing is
 * downloaded ahead.
 */
object MapTiles {
    const val TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png"

    /** The land colour of the board's drawn map, shown while tiles load or when they cannot. */
    const val BACKGROUND = "#ECE8DD"

    private const val MAX_ZOOM = 19
    private const val PROJECT_URL = "https://github.com/SaptooAji/seafood-marketplace"

    fun userAgent(versionName: String) = "SeafoodMarketplaceSeller/$versionName (+$PROJECT_URL)"

    /** The HTTP client MapLibre fetches tiles with; it puts the app's User-Agent on every request. */
    fun httpClient(versionName: String): OkHttpClient {
        val userAgent = userAgent(versionName)
        return OkHttpClient
            .Builder()
            .addInterceptor { chain ->
                chain.proceed(
                    chain
                        .request()
                        .newBuilder()
                        .header("User-Agent", userAgent)
                        .build(),
                )
            }.build()
    }

    /** A style with a plain background under one raster layer of OpenStreetMap tiles. */
    val styleJson =
        """
        {
          "version": 8,
          "sources": {
            "osm": {
              "type": "raster",
              "tiles": ["$TILE_URL"],
              "tileSize": 256,
              "maxzoom": $MAX_ZOOM,
              "attribution": "© OpenStreetMap contributors"
            }
          },
          "layers": [
            {"id": "land", "type": "background", "paint": {"background-color": "$BACKGROUND"}},
            {"id": "osm", "type": "raster", "source": "osm"}
          ]
        }
        """.trimIndent()
}
