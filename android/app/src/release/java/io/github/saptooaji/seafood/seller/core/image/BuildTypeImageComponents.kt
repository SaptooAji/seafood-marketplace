package io.github.saptooaji.seafood.seller.core.image

import coil3.ComponentRegistry

/**
 * The build-type part of the image loader. A release build adds nothing:
 * photo URLs are loaded exactly as the server sends them.
 */
object BuildTypeImageComponents {
    @Suppress("UNUSED_PARAMETER")
    fun install(registry: ComponentRegistry.Builder) = Unit
}
