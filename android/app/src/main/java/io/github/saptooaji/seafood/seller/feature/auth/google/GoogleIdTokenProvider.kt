package io.github.saptooaji.seafood.seller.feature.auth.google

import android.content.Context

/** What the Google account picker ended with. */
sealed interface GoogleIdTokenResult {
    /** A Google ID token for the server to verify; exchanged once, never stored. */
    class Success(
        val idToken: String,
    ) : GoogleIdTokenResult {
        // An ID token is a credential: keep it out of logs and crash reports (MOB-4).
        override fun toString() = "Success(idToken=██)"
    }

    /** The seller closed the picker. Not an error. */
    data object Cancelled : GoogleIdTokenResult

    /** The device has no Google account to pick. */
    data object NoAccount : GoogleIdTokenResult

    /** Anything else Credential Manager reported; the details are not shown. */
    data object Failed : GoogleIdTokenResult
}

/**
 * Asks Google for an ID token through the account picker. Behind an interface
 * so tests can answer without Credential Manager.
 */
interface GoogleIdTokenProvider {
    /** False when the build has no `seafood.googleServerClientId`, as in CI or a fork. */
    val isAvailable: Boolean

    /**
     * @param activityContext the current activity: Credential Manager shows its
     *   picker over it. Used only while the picker is open.
     */
    suspend fun idToken(activityContext: Context): GoogleIdTokenResult
}
