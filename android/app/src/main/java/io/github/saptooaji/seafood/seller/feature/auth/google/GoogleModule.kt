package io.github.saptooaji.seafood.seller.feature.auth.google

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.BuildConfig

@Module
@InstallIn(SingletonComponent::class)
object GoogleModule {
    /** The web client ID comes from `local.properties` (`seafood.googleServerClientId`, MOB-2). */
    @Provides
    fun googleIdTokenProvider(): GoogleIdTokenProvider = CredentialManagerGoogleIdTokenProvider(BuildConfig.GOOGLE_SERVER_CLIENT_ID)
}
