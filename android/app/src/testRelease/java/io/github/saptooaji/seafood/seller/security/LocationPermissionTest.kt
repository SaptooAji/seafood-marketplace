package io.github.saptooaji.seafood.seller.security

import android.content.pm.PackageManager
import androidx.test.core.app.ApplicationProvider
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * The merged manifest, libraries included, asks for the device location
 * only while the app is in use: the map is centred on the seller once, and
 * nothing tracks them in the background.
 */
@RunWith(RobolectricTestRunner::class)
class LocationPermissionTest {
    @Test
    fun theAppAsksForForegroundLocationOnly() {
        val context = ApplicationProvider.getApplicationContext<android.app.Application>()

        val requested =
            context.packageManager
                .getPackageInfo(context.packageName, PackageManager.GET_PERMISSIONS)
                .requestedPermissions
                .orEmpty()
                .toSet()

        assertTrue("android.permission.ACCESS_COARSE_LOCATION" in requested)
        assertTrue("android.permission.ACCESS_FINE_LOCATION" in requested)
        assertFalse("android.permission.ACCESS_BACKGROUND_LOCATION" in requested)
    }
}
