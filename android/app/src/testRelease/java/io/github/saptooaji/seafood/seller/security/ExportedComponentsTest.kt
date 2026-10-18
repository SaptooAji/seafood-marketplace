package io.github.saptooaji.seafood.seller.security

import android.content.pm.ComponentInfo
import android.content.pm.PackageManager
import androidx.test.core.app.ApplicationProvider
import org.junit.Assert.assertEquals
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * MOB-8 on the merged manifest, libraries included: the only component other
 * apps can start without a permission is the launcher activity. Library
 * components guarded by a permission (such as profile installation, which
 * needs DUMP) are allowed.
 */
@RunWith(RobolectricTestRunner::class)
class ExportedComponentsTest {
    @Test
    fun onlyTheMainActivityIsOpenToOtherApps() {
        val context = ApplicationProvider.getApplicationContext<android.app.Application>()

        @Suppress("DEPRECATION")
        val info =
            context.packageManager.getPackageInfo(
                context.packageName,
                PackageManager.GET_ACTIVITIES or PackageManager.GET_SERVICES or
                    PackageManager.GET_RECEIVERS or PackageManager.GET_PROVIDERS,
            )

        val open =
            listOfNotNull(info.activities, info.services, info.receivers, info.providers)
                .flatMap { it.asList() }
                .filter { it.exported && it.requiredPermission() == null }
                .map { it.name }

        assertEquals(listOf("${context.packageName}.MainActivity"), open)
    }

    private fun ComponentInfo.requiredPermission(): String? =
        when (this) {
            is android.content.pm.ActivityInfo -> permission
            is android.content.pm.ServiceInfo -> permission
            is android.content.pm.ProviderInfo -> readPermission ?: writePermission
            else -> null
        }
}
