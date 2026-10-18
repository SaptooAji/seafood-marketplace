package io.github.saptooaji.seafood.seller.feature.auth.google

import android.content.Context
import androidx.credentials.CredentialManager
import androidx.credentials.CustomCredential
import androidx.credentials.GetCredentialRequest
import androidx.credentials.exceptions.GetCredentialCancellationException
import androidx.credentials.exceptions.GetCredentialException
import androidx.credentials.exceptions.NoCredentialException
import com.google.android.libraries.identity.googleid.GetSignInWithGoogleOption
import com.google.android.libraries.identity.googleid.GoogleIdTokenCredential
import com.google.android.libraries.identity.googleid.GoogleIdTokenParsingException

/**
 * "Sign in with Google" through Credential Manager. The picker lists every
 * Google account on the device and lets the seller add one.
 *
 * @param serverClientId the **web** client ID: the ID token is issued for it,
 *   and the server accepts only tokens whose `aud` is in its allow list
 */
class CredentialManagerGoogleIdTokenProvider(
    private val serverClientId: String,
) : GoogleIdTokenProvider {
    override val isAvailable: Boolean get() = serverClientId.isNotBlank()

    override suspend fun idToken(activityContext: Context): GoogleIdTokenResult {
        if (!isAvailable) return GoogleIdTokenResult.Failed

        val request =
            GetCredentialRequest
                .Builder()
                .addCredentialOption(GetSignInWithGoogleOption.Builder(serverClientId).build())
                .build()

        return try {
            val credential = CredentialManager.create(activityContext).getCredential(activityContext, request).credential
            if (credential is CustomCredential && credential.type == GoogleIdTokenCredential.TYPE_GOOGLE_ID_TOKEN_CREDENTIAL) {
                GoogleIdTokenResult.Success(GoogleIdTokenCredential.createFrom(credential.data).idToken)
            } else {
                GoogleIdTokenResult.Failed
            }
        } catch (_: GetCredentialCancellationException) {
            GoogleIdTokenResult.Cancelled
        } catch (_: NoCredentialException) {
            GoogleIdTokenResult.NoAccount
        } catch (_: GetCredentialException) {
            GoogleIdTokenResult.Failed
        } catch (_: GoogleIdTokenParsingException) {
            GoogleIdTokenResult.Failed
        }
    }
}
