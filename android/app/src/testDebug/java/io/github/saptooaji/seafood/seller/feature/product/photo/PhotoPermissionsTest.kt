package io.github.saptooaji.seafood.seller.feature.product.photo

import android.app.Application
import android.content.ComponentName
import android.content.pm.PackageManager
import androidx.core.content.FileProvider
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.xmlpull.v1.XmlPullParser

/**
 * Photos need no dangerous permission (#241, MOB-8): the Photo Picker and the
 * camera app hand the photo over, and only the cache photo folder is shared.
 */
@RunWith(RobolectricTestRunner::class)
class PhotoPermissionsTest {
    private val context = ApplicationProvider.getApplicationContext<Application>()

    @Test
    fun theMergedManifestAsksForNoCameraOrStoragePermission() {
        val requested =
            context.packageManager
                .getPackageInfo(context.packageName, PackageManager.GET_PERMISSIONS)
                .requestedPermissions
                .orEmpty()
                .toSet()

        assertFalse("android.permission.CAMERA" in requested)
        assertFalse("android.permission.READ_MEDIA_IMAGES" in requested)
        assertFalse("android.permission.READ_EXTERNAL_STORAGE" in requested)
        assertFalse("android.permission.WRITE_EXTERNAL_STORAGE" in requested)
    }

    @Test
    fun theFileProviderIsPrivateAndGrantsPerUri() {
        val provider =
            context.packageManager.getProviderInfo(
                ComponentName(context, FileProvider::class.java),
                PackageManager.GET_META_DATA,
            )

        assertFalse(provider.exported)
        assertTrue(provider.grantUriPermissions)
        assertEquals("${context.packageName}.photos", provider.authority)
    }

    @Test
    fun theFileProviderSharesOnlyTheCachePhotoFolder() {
        val paths = mutableListOf<Triple<String, String?, String?>>()
        context.resources.getXml(R.xml.photo_paths).use { parser ->
            while (parser.next() != XmlPullParser.END_DOCUMENT) {
                if (parser.eventType == XmlPullParser.START_TAG && parser.name != "paths") {
                    paths += Triple(parser.name, parser.getAttributeValue(null, "name"), parser.getAttributeValue(null, "path"))
                }
            }
        }

        assertEquals(listOf(Triple("cache-path", "photos", "photos/")), paths)
    }
}
