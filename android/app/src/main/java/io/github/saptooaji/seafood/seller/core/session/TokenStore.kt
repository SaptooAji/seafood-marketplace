package io.github.saptooaji.seafood.seller.core.session

import kotlinx.coroutines.flow.Flow

/**
 * Where the API token lives on the device. The implementation keeps it
 * encrypted (MOB-1); nothing else stores the token.
 */
interface TokenStore {
    /** The current token, or null when signed out. */
    fun token(): Flow<String?>

    suspend fun save(token: String)

    suspend fun clear()
}
