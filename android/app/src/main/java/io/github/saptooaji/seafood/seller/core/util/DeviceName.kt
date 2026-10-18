package io.github.saptooaji.seafood.seller.core.util

import android.os.Build

/**
 * The `device_name` sent when signing in, so the seller can tell their
 * devices apart: "Google Pixel 9", at most 255 characters as the API allows.
 */
object DeviceName {
    private const val MAX_LENGTH = 255

    val current: String by lazy { of(Build.MANUFACTURER.orEmpty(), Build.MODEL.orEmpty()) }

    fun of(
        manufacturer: String,
        model: String,
    ): String {
        val maker = manufacturer.trim().replaceFirstChar { it.titlecase() }
        val name = model.trim()
        val full =
            when {
                name.isEmpty() -> maker
                maker.isEmpty() || name.startsWith(maker, ignoreCase = true) -> name
                else -> "$maker $name"
            }

        return full.ifEmpty { "Android" }.take(MAX_LENGTH)
    }
}
