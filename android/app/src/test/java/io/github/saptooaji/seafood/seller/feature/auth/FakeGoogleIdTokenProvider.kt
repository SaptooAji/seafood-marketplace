package io.github.saptooaji.seafood.seller.feature.auth

import android.content.Context
import io.github.saptooaji.seafood.seller.feature.auth.google.GoogleIdTokenProvider
import io.github.saptooaji.seafood.seller.feature.auth.google.GoogleIdTokenResult
import kotlinx.coroutines.CompletableDeferred

/** A [GoogleIdTokenProvider] whose answer each test sets, instead of Credential Manager. */
class FakeGoogleIdTokenProvider(
    override val isAvailable: Boolean = true,
) : GoogleIdTokenProvider {
    var result: GoogleIdTokenResult = GoogleIdTokenResult.Success(ID_TOKEN)
    var calls = 0

    /** When set, the account picker stays open until it completes. */
    var gate: CompletableDeferred<Unit>? = null

    override suspend fun idToken(activityContext: Context): GoogleIdTokenResult {
        calls++
        gate?.await()
        return result
    }

    companion object {
        const val ID_TOKEN = "eyJhbGciOiJSUzI1NiJ9.google-id-token.signature"
    }
}
