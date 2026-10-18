package io.github.saptooaji.seafood.seller.feature.store

import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.location.DeviceLocation

/** A [DeviceLocation] that answers with [point], or with nothing when it is null. */
class FakeDeviceLocation(
    var point: GeoPoint? = null,
) : DeviceLocation {
    var calls = 0

    override suspend fun current(): GeoPoint? {
        calls++
        return point
    }
}
