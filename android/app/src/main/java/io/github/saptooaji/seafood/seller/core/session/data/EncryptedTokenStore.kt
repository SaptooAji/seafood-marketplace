package io.github.saptooaji.seafood.seller.core.session.data

import androidx.datastore.core.CorruptionException
import androidx.datastore.core.DataStore
import androidx.datastore.core.Serializer
import com.google.crypto.tink.Aead
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import kotlinx.serialization.Serializable
import kotlinx.serialization.SerializationException
import kotlinx.serialization.json.Json
import java.io.InputStream
import java.io.OutputStream
import java.security.GeneralSecurityException

/** What is kept on disk, encrypted as a whole. */
@Serializable
data class StoredSession(
    val token: String? = null,
)

/**
 * The token in a DataStore file whose whole content is encrypted with Tink
 * AEAD (MOB-1). The file lives in `files/datastore/`, which backups leave out
 * (MOB-7).
 */
class EncryptedTokenStore(
    private val dataStore: DataStore<StoredSession>,
) : TokenStore {
    override fun token(): Flow<String?> = dataStore.data.map { it.token }

    override suspend fun save(token: String) {
        dataStore.updateData { it.copy(token = token) }
    }

    override suspend fun clear() {
        dataStore.updateData { StoredSession() }
    }

    companion object {
        /** The DataStore file name, under `files/datastore/`. */
        const val FILE_NAME = "session.pb"
    }
}

/**
 * Encrypts the session with [aead]. The file name is the associated data, so
 * a ciphertext copied from another file does not decrypt. A file that cannot
 * be decrypted (for example after the Keystore key was lost) is treated as
 * signed out rather than crashing the app.
 */
class EncryptedSessionSerializer(
    private val aead: Aead,
) : Serializer<StoredSession> {
    override val defaultValue = StoredSession()

    override suspend fun readFrom(input: InputStream): StoredSession {
        val bytes = input.readBytes()
        if (bytes.isEmpty()) return defaultValue

        return try {
            Json.decodeFromString(StoredSession.serializer(), aead.decrypt(bytes, ASSOCIATED_DATA).decodeToString())
        } catch (e: GeneralSecurityException) {
            throw CorruptionException("The session could not be decrypted", e)
        } catch (e: SerializationException) {
            throw CorruptionException("The session could not be read", e)
        }
    }

    override suspend fun writeTo(
        t: StoredSession,
        output: OutputStream,
    ) {
        output.write(aead.encrypt(Json.encodeToString(StoredSession.serializer(), t).encodeToByteArray(), ASSOCIATED_DATA))
    }

    private companion object {
        val ASSOCIATED_DATA = EncryptedTokenStore.FILE_NAME.encodeToByteArray()
    }
}
