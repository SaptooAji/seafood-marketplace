package io.github.saptooaji.seafood.seller.core.session.data

import android.content.Context
import androidx.datastore.core.DataStoreFactory
import androidx.datastore.core.handlers.ReplaceFileCorruptionHandler
import androidx.datastore.dataStoreFile
import com.google.crypto.tink.Aead
import com.google.crypto.tink.KeyTemplates
import com.google.crypto.tink.RegistryConfiguration
import com.google.crypto.tink.aead.AeadConfig
import com.google.crypto.tink.integration.android.AndroidKeysetManager
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import javax.inject.Singleton

/**
 * The encrypted token store (MOB-1). Tink's AES-256-GCM keyset is kept in its
 * own shared preferences file, encrypted by a key in the Android Keystore that
 * never leaves the device. Both the keyset file and the DataStore file are left
 * out of backups (MOB-7, res/xml/data_extraction_rules.xml).
 */
@Module
@InstallIn(SingletonComponent::class)
object SessionModule {
    /** The shared preferences file holding the encrypted keyset. */
    const val KEYSET_PREFS = "seafood_session_keyset"
    private const val KEYSET_NAME = "session_keyset"
    private const val MASTER_KEY_URI = "android-keystore://seafood_session_master_key"

    @Provides
    @Singleton
    fun tokenStore(
        @ApplicationContext context: Context,
    ): TokenStore {
        AeadConfig.register()
        val aead =
            AndroidKeysetManager
                .Builder()
                .withSharedPref(context, KEYSET_NAME, KEYSET_PREFS)
                .withKeyTemplate(KeyTemplates.get("AES256_GCM"))
                .withMasterKeyUri(MASTER_KEY_URI)
                .build()
                .keysetHandle
                .getPrimitive(RegistryConfiguration.get(), Aead::class.java)

        val dataStore =
            DataStoreFactory.create(
                serializer = EncryptedSessionSerializer(aead),
                // A file that cannot be decrypted means signed out, not a crash.
                corruptionHandler = ReplaceFileCorruptionHandler { StoredSession() },
                produceFile = { context.dataStoreFile(EncryptedTokenStore.FILE_NAME) },
            )

        return EncryptedTokenStore(dataStore)
    }
}
