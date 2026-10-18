package io.github.saptooaji.seafood.seller.core.session.data

import androidx.datastore.core.CorruptionException
import androidx.datastore.core.DataStore
import com.google.crypto.tink.Aead
import com.google.crypto.tink.KeysetHandle
import com.google.crypto.tink.RegistryConfiguration
import com.google.crypto.tink.aead.AeadConfig
import com.google.crypto.tink.aead.PredefinedAeadParameters
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.runBlocking
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.assertThrows
import java.io.ByteArrayInputStream
import java.io.ByteArrayOutputStream

/**
 * The token store and its encrypted file format (MOB-1). On the device the
 * key comes from the Android Keystore (SessionModule); here a Tink key made in
 * memory stands in for it, since the Keystore does not exist on the JVM.
 *
 * The serializer is tested on the bytes it writes, which are exactly what
 * DataStore puts in the file. DataStore itself is replaced by an in-memory
 * fake: its file replacement uses a rename that the JVM cannot do over an
 * existing file on Windows, although it works on Android and on CI.
 */
class EncryptedTokenStoreTest {
    private val aead = newAead()
    private val serializer = EncryptedSessionSerializer(aead)

    // --- The file format --------------------------------------------------

    @Test
    fun `the bytes on disk never hold the token in plain text`() {
        val bytes = write(StoredSession("sfm_abc123"))

        assertTrue(bytes.isNotEmpty())
        assertFalse(String(bytes, Charsets.ISO_8859_1).contains("sfm_"), "the token is stored encrypted")
    }

    @Test
    fun `what is written reads back`() {
        val bytes = write(StoredSession("sfm_abc123"))

        assertEquals(StoredSession("sfm_abc123"), read(bytes))
    }

    @Test
    fun `an empty file is a new install, signed out`() {
        assertEquals(StoredSession(), read(ByteArray(0)))
    }

    @Test
    fun `a file encrypted with another key is reported as corrupt`() {
        // The Keystore key was lost, for example after a restore to a new phone.
        // SessionModule's ReplaceFileCorruptionHandler turns this into signed out.
        val bytes = EncryptedSessionSerializer(newAead()).let { other -> write(StoredSession("sfm_abc123"), other) }

        assertThrows<CorruptionException> { read(bytes) }
    }

    @Test
    fun `a file that is not a session is reported as corrupt`() {
        val bytes = aead.encrypt("not json".encodeToByteArray(), EncryptedTokenStore.FILE_NAME.encodeToByteArray())

        assertThrows<CorruptionException> { read(bytes) }
    }

    // --- The store --------------------------------------------------------

    @Test
    fun `saves and reads the token`() =
        runBlocking {
            val store = EncryptedTokenStore(MemoryDataStore())

            store.save("sfm_abc123")

            assertEquals("sfm_abc123", store.token().first())
        }

    @Test
    fun `clear signs out`() =
        runBlocking {
            val store = EncryptedTokenStore(MemoryDataStore())
            store.save("sfm_abc123")

            store.clear()

            assertNull(store.token().first())
        }

    @Test
    fun `a new install starts signed out`() =
        runBlocking {
            assertNull(EncryptedTokenStore(MemoryDataStore()).token().first())
        }

    private fun write(
        session: StoredSession,
        with: EncryptedSessionSerializer = serializer,
    ): ByteArray = ByteArrayOutputStream().also { runBlocking { with.writeTo(session, it) } }.toByteArray()

    private fun read(bytes: ByteArray): StoredSession = runBlocking { serializer.readFrom(ByteArrayInputStream(bytes)) }

    private fun newAead(): Aead {
        AeadConfig.register()
        return KeysetHandle
            .newBuilder()
            .addEntry(KeysetHandle.generateEntryFromParameters(PredefinedAeadParameters.AES256_GCM).withRandomId().makePrimary())
            .build()
            .getPrimitive(RegistryConfiguration.get(), Aead::class.java)
    }

    private class MemoryDataStore : DataStore<StoredSession> {
        private val state = MutableStateFlow(StoredSession())

        override val data: Flow<StoredSession> = state

        override suspend fun updateData(transform: suspend (t: StoredSession) -> StoredSession): StoredSession =
            transform(state.value).also { state.value = it }
    }
}
