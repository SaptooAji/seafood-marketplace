package io.github.saptooaji.seafood.seller.feature.auth.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/** Creating an account, signing in, and reading the signed-in account. */
interface AuthRepository {
    /** Signs in with email and password; on success the token is stored (MOB-1). */
    suspend fun login(
        email: String,
        password: String,
    ): ApiResult<User>

    /** Creates an account and signs it in; on success the token is stored (MOB-1). */
    suspend fun register(registration: Registration): ApiResult<User>

    /** The account the stored token belongs to (`GET me`). */
    suspend fun me(): ApiResult<User>
}
