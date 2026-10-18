package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.BuildConfig

/**
 * The base URL of the backend API, always ending in `/api/v1/` so Retrofit
 * paths stay relative. Debug builds read `seafood.apiBaseUrl` from
 * `local.properties`; release builds require HTTPS (MOB-3, checked at build
 * time).
 */
object ApiBaseUrl {
    private const val API_PATH = "/api/v1"

    /** The URL this build talks to. */
    val value: String by lazy { normalize(BuildConfig.API_BASE_URL) }

    /**
     * Accepts the host root (`http://localhost:8000`) or the API path, with or
     * without a trailing slash, and returns `<root>/api/v1/`.
     *
     * @throws IllegalArgumentException when the URL is blank or not http(s)
     */
    fun normalize(configured: String): String {
        val url = configured.trim().trimEnd('/')
        require(url.startsWith("http://") || url.startsWith("https://")) {
            "The API base URL must start with http:// or https://, got '$configured'"
        }

        return (if (url.endsWith(API_PATH)) url else url + API_PATH) + "/"
    }
}
