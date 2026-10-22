package io.github.saptooaji.seafood.seller.core.network

import android.net.ConnectivityManager
import android.net.NetworkCapabilities

/** Whether the phone itself reaches the internet, apart from our server (#277). */
fun interface DeviceNetwork {
    fun isOnline(): Boolean
}

/**
 * Online means a network Android has checked reaches the internet
 * (`VALIDATED`), so a Wi-Fi behind a login page counts as offline. When the
 * state cannot be read, the phone counts as online: better to blame the server
 * than to tell the seller to check an internet that works.
 */
class AndroidDeviceNetwork(
    private val connectivity: ConnectivityManager?,
) : DeviceNetwork {
    override fun isOnline(): Boolean {
        val manager = connectivity ?: return true
        return try {
            val network = manager.activeNetwork
            network != null &&
                manager.getNetworkCapabilities(network)?.hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED) == true
        } catch (_: SecurityException) {
            true
        }
    }
}
