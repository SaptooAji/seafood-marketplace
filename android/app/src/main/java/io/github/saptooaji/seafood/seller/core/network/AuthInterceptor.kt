package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import okhttp3.HttpUrl
import okhttp3.Interceptor
import okhttp3.Response

/**
 * Adds `Authorization: Bearer <token>` to requests for the API, and ends the
 * session when the API answers 401 (MOB-6): the token is deleted and
 * [SessionEvents] tells the app to show sign-in. API tokens expire after 90
 * days (#149), so this does happen.
 *
 * The token is sent only to the API host, never to another URL, and not to
 * the sign-in routes, where a 401 or 422 is an ordinary refusal.
 */
class AuthInterceptor(
    private val baseUrl: HttpUrl,
    private val tokenStore: TokenStore,
    private val sessionEvents: SessionEvents,
) : Interceptor {
    private val lock = Any()

    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        if (!isApi(request.url) || isSignIn(request.url)) return chain.proceed(request)

        // OkHttp calls interceptors on its own threads, so blocking here is safe.
        val token = runBlocking { tokenStore.token().first() }
        val response =
            chain.proceed(
                if (token == null) request else request.newBuilder().header("Authorization", "Bearer $token").build(),
            )

        if (response.code == UNAUTHORIZED && token != null) endSession(token)
        return response
    }

    /**
     * Ends the session once, even when several requests fail with the same
     * token at the same time: only the first finds its token still stored.
     */
    private fun endSession(rejectedToken: String) {
        synchronized(lock) {
            runBlocking {
                if (tokenStore.token().first() == rejectedToken) {
                    tokenStore.clear()
                    sessionEvents.signedOut()
                }
            }
        }
    }

    private fun isApi(url: HttpUrl): Boolean =
        url.scheme == baseUrl.scheme &&
            url.host == baseUrl.host &&
            url.port == baseUrl.port &&
            url.encodedPath.startsWith(baseUrl.encodedPath)

    private fun isSignIn(url: HttpUrl): Boolean = url.encodedPath.removePrefix(baseUrl.encodedPath) in SIGN_IN_PATHS

    private companion object {
        const val UNAUTHORIZED = 401
        val SIGN_IN_PATHS = setOf("auth/login", "auth/register", "auth/google")
    }
}
