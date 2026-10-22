package io.github.saptooaji.seafood.seller.feature.product.photo

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Matrix
import android.media.ExifInterface
import android.net.Uri
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import java.io.File
import java.io.IOException
import java.util.UUID
import javax.inject.Inject
import kotlin.math.max
import kotlin.math.min
import kotlin.math.roundToInt

/** A photo's size in pixels. */
data class ImageSize(
    val width: Int,
    val height: Int,
)

/** What preparing a crop gave. */
sealed interface PreparedPhoto {
    /** A JPEG in the cache photo folder, ready to upload. */
    data class Ready(
        val file: File,
        val width: Int,
        val height: Int,
    ) : PreparedPhoto

    /** The crop is under [minSide] pixels on its short side, as the server would refuse. */
    data class TooSmall(
        val minSide: Int,
    ) : PreparedPhoto

    data object Unreadable : PreparedPhoto
}

/** Turns a picked photo into what is uploaded; the crop screen talks to this, tests to a fake. */
interface PhotoPreparer {
    /**
     * Copies the picked photo at [uri] into the cache photo folder, under a
     * new name; a camera shot already there is moved instead. Null when it
     * cannot be read.
     */
    suspend fun import(uri: String): File?

    /** The size of [source] upright, after its EXIF orientation; null when it is not a photo. */
    suspend fun uprightSize(source: File): ImageSize?

    /**
     * [source] turned upright and [quarterTurns] clockwise, cut to [crop]
     * (in the turned photo's pixels), at most [MAX_SIDE] pixels, and saved as
     * a JPEG without EXIF, so no camera location leaves the phone (#241).
     */
    suspend fun prepare(
        source: File,
        crop: PixelRect,
        quarterTurns: Int,
    ): PreparedPhoto
}

/** The longest side of an upload; the server makes it smaller still. */
const val MAX_SIDE = 2400

/**
 * Android's [PhotoPreparer]. Everything stays in `cache/photos`, which only
 * the app and, for one camera shot, the camera app can reach (MOB-8).
 */
class PreparePhoto
    @Inject
    constructor(
        @param:ApplicationContext private val context: Context,
    ) : PhotoPreparer {
        val photoDir: File get() = File(context.cacheDir, PHOTO_FOLDER).apply { mkdirs() }

        override suspend fun import(uri: String): File? =
            withContext(Dispatchers.IO) {
                val target = newFile("source")
                val parsed = Uri.parse(uri)
                val own = parsed.path?.takeIf { parsed.scheme == "file" }?.let(::File)
                if (own != null && own.canonicalFile.parentFile == photoDir.canonicalFile) {
                    // A camera shot is already in the cache: moved, so no copy is left behind.
                    return@withContext target.takeIf { own.renameTo(it) }
                }
                try {
                    val input = context.contentResolver.openInputStream(Uri.parse(uri)) ?: return@withContext null
                    input.use { from -> target.outputStream().use { from.copyTo(it) } }
                    target
                } catch (_: IOException) {
                    target.delete()
                    null
                } catch (_: SecurityException) {
                    // The picker's grant is gone, for one after the process died.
                    target.delete()
                    null
                }
            }

        override suspend fun uprightSize(source: File): ImageSize? =
            withContext(Dispatchers.IO) {
                val bounds = bounds(source) ?: return@withContext null
                if (orientation(source).swapsSides) ImageSize(bounds.height, bounds.width) else bounds
            }

        override suspend fun prepare(
            source: File,
            crop: PixelRect,
            quarterTurns: Int,
        ): PreparedPhoto =
            withContext(Dispatchers.IO) {
                if (crop.shortSide < PHOTO_MIN_SIDE) return@withContext PreparedPhoto.TooSmall(PHOTO_MIN_SIDE)
                val stored = bounds(source) ?: return@withContext PreparedPhoto.Unreadable
                val turned = turn(decode(source, stored, crop) ?: return@withContext PreparedPhoto.Unreadable, source, quarterTurns)
                val scale = turned.width.toFloat() / turnedWidth(stored, orientation(source), quarterTurns)
                val cut = cut(turned, crop, scale)
                val out = newFile("photo")
                out.outputStream().use { cut.compress(Bitmap.CompressFormat.JPEG, JPEG_QUALITY, it) }
                PreparedPhoto.Ready(out, cut.width, cut.height)
            }

        private fun newFile(prefix: String) = File(photoDir, "$prefix-${UUID.randomUUID()}.jpg")

        private fun bounds(file: File): ImageSize? {
            val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
            BitmapFactory.decodeFile(file.path, options)
            return if (options.outWidth > 0 && options.outHeight > 0) ImageSize(options.outWidth, options.outHeight) else null
        }

        private fun orientation(file: File): Orientation =
            try {
                Orientation.of(ExifInterface(file.path).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_NORMAL))
            } catch (_: IOException) {
                Orientation.Normal
            }

        /**
         * The whole photo, sampled down while the crop still has at least
         * [MAX_SIDE] pixels on its long side, to spare memory with a 50 MP
         * camera photo.
         */
        private fun decode(
            file: File,
            stored: ImageSize,
            crop: PixelRect,
        ): Bitmap? {
            var sample = 1
            while (max(crop.width, crop.height) / (sample * 2) >= MAX_SIDE &&
                min(crop.width, crop.height) / (sample * 2) >= PHOTO_MIN_SIDE &&
                min(stored.width, stored.height) / (sample * 2) > 0
            ) {
                sample *= 2
            }
            return BitmapFactory.decodeFile(file.path, BitmapFactory.Options().apply { inSampleSize = sample })
        }

        /** Upright, then the seller's quarter turns. */
        private fun turn(
            bitmap: Bitmap,
            file: File,
            quarterTurns: Int,
        ): Bitmap {
            val matrix = orientation(file).matrix().apply { postRotate(QUARTER * quarterTurns) }
            return if (matrix.isIdentity) bitmap else Bitmap.createBitmap(bitmap, 0, 0, bitmap.width, bitmap.height, matrix, true)
        }

        private fun turnedWidth(
            stored: ImageSize,
            orientation: Orientation,
            quarterTurns: Int,
        ): Int {
            val upright = if (orientation.swapsSides) stored.height else stored.width
            val uprightOther = if (orientation.swapsSides) stored.width else stored.height
            return if (quarterTurns % 2 == 0) upright else uprightOther
        }

        /** [crop] given in full-size pixels, on a bitmap [scale] times that size, then fitted within [MAX_SIDE]. */
        private fun cut(
            turned: Bitmap,
            crop: PixelRect,
            scale: Float,
        ): Bitmap {
            val left = (crop.left * scale).roundToInt().coerceIn(0, turned.width - 1)
            val top = (crop.top * scale).roundToInt().coerceIn(0, turned.height - 1)
            val width = (crop.width * scale).roundToInt().coerceIn(1, turned.width - left)
            val height = (crop.height * scale).roundToInt().coerceIn(1, turned.height - top)
            val fit = min(1f, MAX_SIDE.toFloat() / max(width, height))
            val matrix = Matrix().apply { if (fit < 1f) setScale(fit, fit) }
            return Bitmap.createBitmap(turned, left, top, width, height, matrix, true)
        }

        private companion object {
            const val PHOTO_FOLDER = "photos"
            const val JPEG_QUALITY = 85
            const val QUARTER = 90f
        }
    }

/** The EXIF orientations a camera writes; mirrored ones are rare and treated as their turn. */
private enum class Orientation {
    Normal,
    Quarter,
    Half,
    ThreeQuarters,
    ;

    val swapsSides: Boolean get() = this == Quarter || this == ThreeQuarters

    /** Clockwise, a quarter turn per step. */
    fun matrix() = Matrix().apply { if (this@Orientation != Normal) postRotate(ordinal * QUARTER_DEGREES) }

    companion object {
        fun of(exif: Int): Orientation =
            when (exif) {
                ExifInterface.ORIENTATION_ROTATE_90, ExifInterface.ORIENTATION_TRANSPOSE -> Quarter
                ExifInterface.ORIENTATION_ROTATE_180, ExifInterface.ORIENTATION_FLIP_VERTICAL -> Half
                ExifInterface.ORIENTATION_ROTATE_270, ExifInterface.ORIENTATION_TRANSVERSE -> ThreeQuarters
                else -> Normal
            }
    }
}

private const val QUARTER_DEGREES = 90f
