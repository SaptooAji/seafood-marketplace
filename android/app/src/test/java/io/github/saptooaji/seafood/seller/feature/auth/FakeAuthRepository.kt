package io.github.saptooaji.seafood.seller.feature.auth

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.auth.domain.AuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.Registration
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.coroutines.CompletableDeferred

/** An [AuthRepository] whose answers each test sets, recording every call. */
class FakeAuthRepository : AuthRepository {
    val loginCalls = mutableListOf<Pair<String, String>>()
    var loginResult: ApiResult<User> = ApiResult.Success(BUDI)

    /** When set, login waits for it, so a test can look at the state mid-request. */
    var loginGate: CompletableDeferred<Unit>? = null

    val registerCalls = mutableListOf<Registration>()
    var registerResult: ApiResult<User> = ApiResult.Success(BUDI)

    /** When set, register waits for it, so a test can look at the state mid-request. */
    var registerGate: CompletableDeferred<Unit>? = null

    val googleCalls = mutableListOf<String>()
    var googleResult: ApiResult<User> = ApiResult.Success(BUDI)

    var meCalls = 0
    var meResult: ApiResult<User> = ApiResult.Success(BUDI)

    override suspend fun login(
        email: String,
        password: String,
    ): ApiResult<User> {
        loginCalls += email to password
        loginGate?.await()
        return loginResult
    }

    override suspend fun register(registration: Registration): ApiResult<User> {
        registerCalls += registration
        registerGate?.await()
        return registerResult
    }

    override suspend fun loginWithGoogle(idToken: String): ApiResult<User> {
        googleCalls += idToken
        return googleResult
    }

    var logoutCalls = 0

    /** Whether the stored token was cleared, as the real logout always does. */
    var tokenCleared = false

    override suspend fun logout() {
        logoutCalls++
        tokenCleared = true
    }

    override suspend fun me(): ApiResult<User> {
        meCalls++
        return meResult
    }

    companion object {
        val BUDI = User(id = 1, name = "Budi Santoso", email = "budi@example.com", phone = "081234567890", avatar = null)
    }
}
