package io.github.saptooaji.seafood.seller.feature.store.location

import android.Manifest
import android.annotation.SuppressLint
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationManager
import android.os.CancellationSignal
import androidx.core.content.ContextCompat
import androidx.core.location.LocationManagerCompat
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import kotlinx.coroutines.suspendCancellableCoroutine
import kotlinx.coroutines.withTimeoutOrNull
import kotlin.coroutines.resume

/**
 * Where the device is, used once to centre the store map. The point is never
 * sent; the seller places the pin, and the pin is what the server gets.
 */
interface DeviceLocation {
    /** The current location, or null without permission, a provider, or a fix in time. */
    suspend fun current(): GeoPoint?
}

/**
 * [DeviceLocation] from the platform's [LocationManager], so the app needs no
 * Play Services location library. Asks for one fix and stops.
 */
class LocationManagerDeviceLocation(
    private val context: Context,
) : DeviceLocation {
    // Each provider is used only after the permission it needs is checked.
    @SuppressLint("MissingPermission")
    override suspend fun current(): GeoPoint? {
        val manager = ContextCompat.getSystemService(context, LocationManager::class.java)
        val provider = manager?.let(::provider) ?: return null

        val fix = withTimeoutOrNull(TIMEOUT_MILLIS) { currentFix(manager, provider) } ?: manager.getLastKnownLocation(provider)
        return fix?.let { GeoPoint(it.latitude, it.longitude) }
    }

    private fun provider(manager: LocationManager): String? {
        val fine = granted(Manifest.permission.ACCESS_FINE_LOCATION)
        val coarse = fine || granted(Manifest.permission.ACCESS_COARSE_LOCATION)
        return when {
            coarse && manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER) -> LocationManager.NETWORK_PROVIDER
            fine && manager.isProviderEnabled(LocationManager.GPS_PROVIDER) -> LocationManager.GPS_PROVIDER
            else -> null
        }
    }

    private fun granted(permission: String) = ContextCompat.checkSelfPermission(context, permission) == PackageManager.PERMISSION_GRANTED

    @SuppressLint("MissingPermission")
    private suspend fun currentFix(
        manager: LocationManager,
        provider: String,
    ): Location? =
        suspendCancellableCoroutine { continuation ->
            val cancel = CancellationSignal()
            continuation.invokeOnCancellation { cancel.cancel() }
            LocationManagerCompat.getCurrentLocation(
                manager,
                provider,
                cancel,
                ContextCompat.getMainExecutor(context),
            ) { location -> continuation.resume(location) }
        }

    private companion object {
        const val TIMEOUT_MILLIS = 10_000L
    }
}
