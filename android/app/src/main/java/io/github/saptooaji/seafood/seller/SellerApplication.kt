package io.github.saptooaji.seafood.seller

import android.app.Application
import coil3.ImageLoader
import coil3.PlatformContext
import coil3.SingletonImageLoader
import coil3.network.okhttp.OkHttpNetworkFetcherFactory
import dagger.hilt.android.HiltAndroidApp
import io.github.saptooaji.seafood.seller.core.image.BuildTypeImageComponents

/**
 * The seller app. Hilt builds the dependency graph from here, and Coil asks
 * here for its image loader. Photos are public files on the server, so they
 * are fetched without the API token.
 */
@HiltAndroidApp
class SellerApplication :
    Application(),
    SingletonImageLoader.Factory {
    override fun newImageLoader(context: PlatformContext): ImageLoader =
        ImageLoader
            .Builder(context)
            .components {
                add(OkHttpNetworkFetcherFactory())
                BuildTypeImageComponents.install(this)
            }.build()
}
