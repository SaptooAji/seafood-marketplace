package io.github.saptooaji.seafood.seller.feature.product.photo

/** Test tags of the crop screen. */
object PhotoCropTags {
    const val USE = "photo_crop_use"

    fun aspect(aspect: CropAspect) = "photo_crop_aspect_${aspect.name.lowercase()}"
}
