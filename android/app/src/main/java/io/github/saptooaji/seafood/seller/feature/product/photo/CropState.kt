package io.github.saptooaji.seafood.seller.feature.product.photo

import kotlin.math.min
import kotlin.math.roundToInt

/** The shorter side a product photo needs, as the server's `catalog.photo_min_side` (#236). */
const val PHOTO_MIN_SIDE = 600

/** The crop shapes the server takes (1:1 up to 4:3), as the board names them. */
enum class CropAspect(
    val width: Int,
    val height: Int,
) {
    Square(1, 1),
    Wide(WIDE_WIDTH, WIDE_HEIGHT),
}

private const val WIDE_WIDTH = 4
private const val WIDE_HEIGHT = 3

/** A rectangle in the pixels of the (turned) photo. */
data class PixelRect(
    val left: Int,
    val top: Int,
    val width: Int,
    val height: Int,
) {
    val shortSide: Int get() = min(width, height)
}

/**
 * The crop of the crop screen in the photo's own pixels. The frame keeps
 * [aspect] and always lies inside the photo: [zoom] 1 is the largest such
 * frame, a higher zoom a smaller one around ([centerX], [centerY]).
 *
 * @param imageWidth and [imageHeight] of the photo upright, before the
 *   seller's [quarterTurns] clockwise
 */
data class CropState(
    val imageWidth: Int,
    val imageHeight: Int,
    val quarterTurns: Int = 0,
    val aspect: CropAspect = CropAspect.Square,
    val zoom: Float = 1f,
    val centerX: Float = turnedSize(imageWidth, imageHeight, quarterTurns).first / 2f,
    val centerY: Float = turnedSize(imageWidth, imageHeight, quarterTurns).second / 2f,
) {
    val turnedWidth: Int get() = turnedSize(imageWidth, imageHeight, quarterTurns).first
    val turnedHeight: Int get() = turnedSize(imageWidth, imageHeight, quarterTurns).second

    /** The frame at zoom 1: the largest of [aspect] that fits. */
    private val fullWidth: Float
        get() = min(turnedWidth.toFloat(), turnedHeight.toFloat() * aspect.width / aspect.height)

    private val cropWidth: Float get() = fullWidth / zoom
    private val cropHeight: Float get() = cropWidth * aspect.height / aspect.width

    fun rect(): PixelRect {
        val width = cropWidth.roundToInt()
        val height = cropHeight.roundToInt()
        val left = (centerX - cropWidth / 2).roundToInt().coerceIn(0, turnedWidth - width)
        val top = (centerY - cropHeight / 2).roundToInt().coerceIn(0, turnedHeight - height)
        return PixelRect(left, top, width, height)
    }

    /** Under [PHOTO_MIN_SIDE]: the server would refuse it, so the screen says so first. */
    fun isTooSmall(minSide: Int = PHOTO_MIN_SIDE): Boolean = rect().shortSide < minSide

    /** The frame of [aspect], starting over at zoom 1 in the middle. */
    fun withAspect(aspect: CropAspect): CropState = CropState(imageWidth, imageHeight, quarterTurns, aspect)

    /** A quarter turn clockwise, starting over at zoom 1 in the middle. */
    fun rotated(): CropState = CropState(imageWidth, imageHeight, (quarterTurns + 1) % TURNS, aspect)

    /** Zoomed by [factor] around the same centre, never below the full frame. */
    fun zoomedBy(factor: Float): CropState = copy(zoom = (zoom * factor).coerceIn(1f, MAX_ZOOM)).clamped()

    /** Moved by ([dx], [dy]) photo pixels, stopping at the photo's edges. */
    fun pannedBy(
        dx: Float,
        dy: Float,
    ): CropState = copy(centerX = centerX + dx, centerY = centerY + dy).clamped()

    private fun clamped(): CropState =
        copy(
            centerX = centerX.coerceIn(cropWidth / 2, turnedWidth - cropWidth / 2),
            centerY = centerY.coerceIn(cropHeight / 2, turnedHeight - cropHeight / 2),
        )

    companion object {
        private const val TURNS = 4
        private const val MAX_ZOOM = 8f

        fun of(
            width: Int,
            height: Int,
        ) = CropState(width, height)
    }
}

private fun turnedSize(
    width: Int,
    height: Int,
    quarterTurns: Int,
): Pair<Int, Int> = if (quarterTurns % 2 == 0) width to height else height to width
