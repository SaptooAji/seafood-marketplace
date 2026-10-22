package io.github.saptooaji.seafood.seller.core.network

import okhttp3.Interceptor
import okhttp3.Response
import retrofit2.Invocation
import java.util.concurrent.TimeUnit

/**
 * How long one endpoint may take to answer, when it is slower than the
 * client's default: the AI description waits on Gemini (#240).
 */
@Target(AnnotationTarget.FUNCTION)
@Retention(AnnotationRetention.RUNTIME)
annotation class ReadTimeout(
    val seconds: Int,
)

/** Applies [ReadTimeout] from the Retrofit method behind the request; other requests keep the default. */
class ReadTimeoutInterceptor : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        val seconds =
            request
                .tag(Invocation::class.java)
                ?.method()
                ?.getAnnotation(ReadTimeout::class.java)
                ?.seconds
                ?: return chain.proceed(request)
        return chain.withReadTimeout(seconds, TimeUnit.SECONDS).proceed(request)
    }
}
