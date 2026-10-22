package io.github.saptooaji.seafood.seller.core.network

import android.net.ConnectivityManager
import android.net.Network
import android.net.NetworkCapabilities
import io.mockk.every
import io.mockk.mockk
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

/**
 * Whether the phone itself is online (#277): only a network Android has
 * checked reaches the internet. When the state cannot be read, the phone
 * counts as online, so the seller is not told to check an internet that works.
 */
class AndroidDeviceNetworkTest {
    private val network = mockk<Network>()

    private fun connectivity(validated: Boolean?): ConnectivityManager =
        mockk {
            every { activeNetwork } returns network
            every { getNetworkCapabilities(network) } returns
                validated?.let { isValidated ->
                    mockk<NetworkCapabilities> {
                        every { hasCapability(NetworkCapabilities.NET_CAPABILITY_VALIDATED) } returns isValidated
                    }
                }
        }

    @Test
    fun `a network Android has checked is online`() {
        assertTrue(AndroidDeviceNetwork(connectivity(validated = true)).isOnline())
    }

    @Test
    fun `a network without internet, such as a captive portal, is offline`() {
        assertFalse(AndroidDeviceNetwork(connectivity(validated = false)).isOnline())
    }

    @Test
    fun `a network with no capabilities is offline`() {
        assertFalse(AndroidDeviceNetwork(connectivity(validated = null)).isOnline())
    }

    @Test
    fun `no active network is offline`() {
        val none = mockk<ConnectivityManager> { every { activeNetwork } returns null }

        assertFalse(AndroidDeviceNetwork(none).isOnline())
    }

    @Test
    fun `no connectivity service counts as online`() {
        assertTrue(AndroidDeviceNetwork(null).isOnline())
    }

    @Test
    fun `a state the app may not read counts as online`() {
        val refused = mockk<ConnectivityManager> { every { activeNetwork } throws SecurityException("ACCESS_NETWORK_STATE") }

        assertTrue(AndroidDeviceNetwork(refused).isOnline())
    }
}
