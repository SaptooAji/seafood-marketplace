package io.github.saptooaji.seafood.seller.feature.store.data

import android.content.Context
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.location.DeviceLocation
import io.github.saptooaji.seafood.seller.feature.store.location.LocationManagerDeviceLocation
import kotlinx.serialization.json.Json
import retrofit2.Retrofit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object StoreModule {
    @Provides
    @Singleton
    fun storeApi(retrofit: Retrofit): StoreApi = retrofit.create(StoreApi::class.java)

    @Provides
    @Singleton
    fun storeRepository(
        api: StoreApi,
        json: Json,
    ): StoreRepository = StoreRepositoryImpl(api, json)

    @Provides
    fun deviceLocation(
        @ApplicationContext context: Context,
    ): DeviceLocation = LocationManagerDeviceLocation(context)
}
