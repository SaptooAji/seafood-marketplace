package io.github.saptooaji.seafood.seller.feature.auth.data

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import io.github.saptooaji.seafood.seller.core.util.DeviceName
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import kotlinx.serialization.json.Json
import retrofit2.Retrofit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AuthModule {
    @Provides
    @Singleton
    fun authApi(retrofit: Retrofit): AuthApi = retrofit.create(AuthApi::class.java)

    @Provides
    @Singleton
    fun authRepository(
        api: AuthApi,
        json: Json,
        tokenStore: TokenStore,
    ): AuthRepository = AuthRepositoryImpl(api, json, tokenStore, DeviceName.current)
}
