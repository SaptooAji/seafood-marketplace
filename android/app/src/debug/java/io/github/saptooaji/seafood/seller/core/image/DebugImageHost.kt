package io.github.saptooaji.seafood.seller.core.image

import coil3.ComponentRegistry
import coil3.intercept.Interceptor
import coil3.request.ImageResult
import io.github.saptooaji.seafood.seller.core.network.ApiBaseUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull

/**
 * Debug builds only. The server builds photo URLs from its `APP_URL`
 * (`http://localhost:8000/storage/...`), which an emulator cannot reach; the
 * photos are fetched from the host the API is reached at instead, such as
 * `10.0.2.2:8000`. Release builds use photo URLs exactly as sent.
 */
object DebugImageHost {
    /** [url] with the scheme, host, and port of [apiBaseUrl]; anything that is not an http(s) URL is left alone. */
    fun rewrite(
        url: String,
        apiBaseUrl: String,
    ): String {
        val image = url.toHttpUrlOrNull() ?: return url
        val api = apiBaseUrl.toHttpUrlOrNull() ?: return url
        return image
            .newBuilder()
            .scheme(api.scheme)
            .host(api.host)
            .port(api.port)
            .build()
            .toString()
    }

    private class RewriteInterceptor : Interceptor {
        override suspend fun intercept(chain: Interceptor.Chain): ImageResult {
            val data = chain.request.data
            if (data !is String) return chain.proceed()
            val request =
                chain.request
                    .newBuilder()
                    .data(rewrite(data, ApiBaseUrl.value))
                    .build()
            return chain.withRequest(request).proceed()
        }
    }

    fun install(registry: ComponentRegistry.Builder) {
        registry.add(RewriteInterceptor())
    }
}

/** The build-type part of the image loader: in debug, the host rewrite. */
object BuildTypeImageComponents {
    fun install(registry: ComponentRegistry.Builder) = DebugImageHost.install(registry)
}
