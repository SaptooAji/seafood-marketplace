package io.github.saptooaji.seafood.seller.feature.product.data

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPhotoRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import kotlinx.serialization.json.Json
import retrofit2.Retrofit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object ProductModule {
    @Provides
    @Singleton
    fun productApi(retrofit: Retrofit): ProductApi = retrofit.create(ProductApi::class.java)

    @Provides
    @Singleton
    fun productRepository(
        api: ProductApi,
        json: Json,
    ): ProductRepository = ProductRepositoryImpl(api, json)

    @Provides
    @Singleton
    fun productPhotoApi(retrofit: Retrofit): ProductPhotoApi = retrofit.create(ProductPhotoApi::class.java)

    @Provides
    @Singleton
    fun productPhotoRepository(
        api: ProductPhotoApi,
        json: Json,
    ): ProductPhotoRepository = ProductPhotoRepositoryImpl(api, json)

    @Provides
    @Singleton
    fun categoryApi(retrofit: Retrofit): CategoryApi = retrofit.create(CategoryApi::class.java)

    /** A singleton, so the tree is fetched once a session. */
    @Provides
    @Singleton
    fun categoryRepository(
        api: CategoryApi,
        json: Json,
    ): CategoryRepository = CategoryRepositoryImpl(api, json)
}
