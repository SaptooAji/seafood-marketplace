package io.github.saptooaji.seafood.seller.feature.store.domain

import java.math.BigDecimal
import java.math.RoundingMode

/**
 * What a seller fills in to open or edit a store (`StoreRequest`). The
 * coordinates have seven decimals, as the `decimal(10,7)` columns hold them.
 */
data class StoreDetails(
    val name: String,
    val description: String?,
    val phone: String,
    val address: String,
    val city: String,
    val lat: BigDecimal,
    val lng: BigDecimal,
    val bankName: String,
    val bankAccountNo: String,
    val bankAccountName: String,
) {
    // The account is not a secret to its owner, but it stays out of logs (MOB-4).
    override fun toString() =
        "StoreDetails(name=$name, city=$city, lat=$lat, lng=$lng, bankName=$bankName, bankAccountNo=██, bankAccountName=██)"
}

/** A point on the map, as the map library reports it. */
data class GeoPoint(
    val lat: Double,
    val lng: Double,
) {
    /** [lat] rounded half up to the seven decimals the server keeps. */
    val scaledLat: BigDecimal get() = scale(lat)

    /** [lng] rounded half up to the seven decimals the server keeps. */
    val scaledLng: BigDecimal get() = scale(lng)

    companion object {
        const val DECIMALS = 7

        /** Where the map opens when the device location is not used. */
        val JAKARTA_UTARA = GeoPoint(-6.1214, 106.8829)

        // valueOf goes through the shortest decimal form of the double, so
        // -6.1067 stays -6.1067 instead of its binary expansion.
        private fun scale(value: Double): BigDecimal = BigDecimal.valueOf(value).setScale(DECIMALS, RoundingMode.HALF_UP)
    }
}
