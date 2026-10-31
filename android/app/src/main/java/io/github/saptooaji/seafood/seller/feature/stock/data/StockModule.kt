package io.github.saptooaji.seafood.seller.feature.stock.data

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import kotlinx.serialization.json.Json
import retrofit2.Retrofit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object StockModule {
    @Provides
    @Singleton
    fun stockApi(retrofit: Retrofit): StockApi = retrofit.create(StockApi::class.java)

    @Provides
    @Singleton
    fun stockRepository(
        api: StockApi,
        json: Json,
    ): StockRepository = StockRepositoryImpl(api, json)
}
