package io.github.saptooaji.seafood.seller.feature.auth.data

import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/** The auth endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface AuthApi {
    @POST("auth/login")
    suspend fun login(
        @Body request: LoginRequest,
    ): Response<TokenResponse>

    @POST("auth/google")
    suspend fun loginWithGoogle(
        @Body request: GoogleLoginRequest,
    ): Response<TokenResponse>

    @POST("auth/register")
    suspend fun register(
        @Body request: RegisterRequest,
    ): Response<TokenResponse>

    @POST("auth/logout")
    suspend fun logout(): Response<Unit>

    @GET("me")
    suspend fun me(): Response<DataEnvelope<UserDto>>
}
