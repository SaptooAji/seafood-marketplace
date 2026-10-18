package io.github.saptooaji.seafood.seller.feature.auth.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.serialization.json.Json

/**
 * @param deviceName sent as `device_name`, so the seller can tell their
 *   devices apart in the server's token list
 */
class AuthRepositoryImpl(
    private val api: AuthApi,
    private val json: Json,
    private val tokenStore: TokenStore,
    private val deviceName: String,
) : AuthRepository {
    override suspend fun login(
        email: String,
        password: String,
    ): ApiResult<User> {
        val result = apiCall(json) { api.login(LoginRequest(email, password, deviceName)) }
        if (result is ApiResult.Success) tokenStore.save(result.data.token)

        return result.map { it.user.toDomain() }
    }

    override suspend fun me(): ApiResult<User> = apiCall(json) { api.me() }.map { it.data.toDomain() }
}
