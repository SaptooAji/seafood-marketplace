package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import okhttp3.HttpUrl
import okhttp3.Interceptor
import okhttp3.Response

/**
 * Tells the app when a seller endpoint answers 403: the server refuses every
 * seller request of a suspended store (`EnsureStoreIsNotSuspended`, AC-7), so
 * whichever screen made the request, the seller is shown the store status.
 * The response still reaches the caller unchanged.
 */
class StoreSuspendedInterceptor(
    private val baseUrl: HttpUrl,
    private val sessionEvents: SessionEvents,
    private val json: Json,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val response = chain.proceed(chain.request())
        val url = response.request.url

        if (response.code == FORBIDDEN && url.isUnder(baseUrl) && url.encodedPath.removePrefix(baseUrl.encodedPath).startsWith(SELLER)) {
            sessionEvents.storeSuspended(message(response))
        }
        return response
    }

    /** The server's `message`, read from a copy so the caller still gets the whole body. */
    private fun message(response: Response): String? {
        val body = runCatching { response.peekBody(MAX_BODY_BYTES).string() }.getOrNull() ?: return null
        val root = runCatching { json.parseToJsonElement(body) }.getOrNull() as? JsonObject
        return (root?.get("message") as? JsonPrimitive)?.takeIf { it.isString }?.content
    }

    private companion object {
        const val FORBIDDEN = 403
        const val SELLER = "seller/"
        const val MAX_BODY_BYTES = 4_096L
    }
}
