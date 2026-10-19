package io.github.saptooaji.seafood.seller.core.network

import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import kotlinx.serialization.json.Json
import okhttp3.HttpUrl
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory
import java.util.concurrent.TimeUnit

/**
 * Builds the one HTTP stack every feature uses. Kept free of Hilt so tests
 * can build the same stack against a MockWebServer.
 */
object HttpClients {
    private const val CONNECT_TIMEOUT_SECONDS = 15L
    private const val READ_TIMEOUT_SECONDS = 30L

    /** Unknown fields are ignored, so the server can add fields without breaking the app. */
    val json =
        Json {
            ignoreUnknownKeys = true
            explicitNulls = false
        }

    /**
     * @param debugLogger where HTTP logs go in a debug build; null in a release
     *   build, where nothing is logged (MOB-4)
     */
    fun okHttpClient(
        baseUrl: HttpUrl,
        tokenStore: TokenStore,
        sessionEvents: SessionEvents,
        debugLogger: HttpLoggingInterceptor.Logger?,
    ): OkHttpClient =
        OkHttpClient
            .Builder()
            .connectTimeout(CONNECT_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            .readTimeout(READ_TIMEOUT_SECONDS, TimeUnit.SECONDS)
            .addInterceptor { chain ->
                chain.proceed(
                    chain
                        .request()
                        .newBuilder()
                        .header("Accept", "application/json")
                        .build(),
                )
            }.addInterceptor(AuthInterceptor(baseUrl, tokenStore, sessionEvents))
            .addInterceptor(StoreSuspendedInterceptor(baseUrl, sessionEvents, json))
            .apply {
                if (debugLogger != null) {
                    addInterceptor(
                        HttpLoggingInterceptor(RedactingLogger(debugLogger)).apply {
                            level = HttpLoggingInterceptor.Level.BODY
                            redactHeader("Authorization")
                        },
                    )
                }
            }.build()

    fun retrofit(
        baseUrl: HttpUrl,
        client: OkHttpClient,
    ): Retrofit =
        Retrofit
            .Builder()
            .baseUrl(baseUrl)
            .client(client)
            .addConverterFactory(json.asConverterFactory("application/json".toMediaType()))
            .build()
}

/**
 * Masks secret JSON fields in logged request and response bodies (MOB-4): the
 * password, the Google ID token, the API token in a sign-in response, and the
 * bank account number, sent as `bank_account_no` and read back as
 * `bank.account_no`. Headers are masked by `redactHeader`.
 */
class RedactingLogger(
    private val delegate: HttpLoggingInterceptor.Logger,
) : HttpLoggingInterceptor.Logger {
    override fun log(message: String) {
        delegate.log(SECRET_FIELD.replace(message) { "\"${it.groupValues[1]}\":\"$MASK\"" })
    }

    private companion object {
        const val MASK = "██"
        val SECRET_FIELD =
            Regex(
                "\"(password|password_confirmation|id_token|token|bank_account_no|account_no)\"\\s*:\\s*\"(?:[^\"\\\\]|\\\\.)*\"",
            )
    }
}
