package io.github.saptooaji.seafood.seller.feature.auth.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/** Creating an account, signing in, and reading the signed-in account. */
interface AuthRepository {
    /** Signs in with email and password; on success the token is stored (MOB-1). */
    suspend fun login(
        email: String,
        password: String,
    ): ApiResult<User>

    /**
     * Trades a Google ID token for an API token (`POST auth/google`). The
     * server links or creates the account; only the API token is stored.
     */
    suspend fun loginWithGoogle(idToken: String): ApiResult<User>

    /** Creates an account and signs it in; on success the token is stored (MOB-1). */
    suspend fun register(registration: Registration): ApiResult<User>

    /**
     * Signs out: revokes the token on the server (`POST auth/logout`) and
     * deletes it from the device even when that request fails (MOB-6).
     */
    suspend fun logout()

    /** The account the stored token belongs to (`GET me`). */
    suspend fun me(): ApiResult<User>

    /** Changes the name and phone of the account (`PUT me`); the email cannot change here. */
    suspend fun updateProfile(
        name: String,
        phone: String,
    ): ApiResult<User>
}
