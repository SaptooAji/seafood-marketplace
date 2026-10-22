package io.github.saptooaji.seafood.seller.core.network

import okhttp3.Interceptor
import okhttp3.Response
import java.io.IOException

/** A request that got no answer while the phone was online; [apiCall] maps it to [ApiResult.ServerUnreachable]. */
class ServerUnreachableException(
    cause: IOException,
) : IOException(cause)

/**
 * The outermost interceptor: when a request fails without an answer, asks
 * [deviceNetwork] whether the phone is online, so the app can tell a server
 * outage from no connection (#277). Only failures ask; answered requests,
 * errors included, pass through untouched.
 */
class ReachabilityInterceptor(
    private val deviceNetwork: DeviceNetwork,
) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response =
        try {
            chain.proceed(chain.request())
        } catch (e: ServerUnreachableException) {
            throw e
        } catch (e: IOException) {
            if (chain.call().isCanceled() || !deviceNetwork.isOnline()) throw e
            throw ServerUnreachableException(e)
        }
}
