package io.github.saptooaji.seafood.seller.core.network

import android.content.Context
import android.net.ConnectivityManager
import android.util.Log
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.BuildConfig
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import kotlinx.serialization.json.Json
import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import javax.inject.Singleton

/**
 * The HTTP stack for every feature. Feature modules ask for [Retrofit] and
 * create their own service interface from it.
 */
@Module
@InstallIn(SingletonComponent::class)
object NetworkModule {
    @Provides
    @Singleton
    fun baseUrl(): HttpUrl = ApiBaseUrl.value.toHttpUrl()

    @Provides
    fun json(): Json = HttpClients.json

    @Provides
    @Singleton
    fun deviceNetwork(
        @ApplicationContext context: Context,
    ): DeviceNetwork = AndroidDeviceNetwork(context.getSystemService(ConnectivityManager::class.java))

    @Provides
    @Singleton
    fun okHttpClient(
        baseUrl: HttpUrl,
        tokenStore: TokenStore,
        sessionEvents: SessionEvents,
        deviceNetwork: DeviceNetwork,
    ): OkHttpClient =
        HttpClients.okHttpClient(
            baseUrl = baseUrl,
            tokenStore = tokenStore,
            sessionEvents = sessionEvents,
            // HTTP logs only in debug builds (MOB-4).
            debugLogger = if (BuildConfig.DEBUG) HttpLoggingInterceptor.Logger { Log.d("Http", it) } else null,
            deviceNetwork = deviceNetwork,
        )

    @Provides
    @Singleton
    fun retrofit(
        baseUrl: HttpUrl,
        client: OkHttpClient,
    ): Retrofit = HttpClients.retrofit(baseUrl, client)
}
