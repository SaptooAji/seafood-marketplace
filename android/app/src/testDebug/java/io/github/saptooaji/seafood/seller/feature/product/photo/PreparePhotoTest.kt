package io.github.saptooaji.seafood.seller.feature.product.photo

import android.app.Application
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.graphics.Canvas
import android.graphics.Color
import android.graphics.Paint
import android.media.ExifInterface
import android.net.Uri
import androidx.test.core.app.ApplicationProvider
import kotlinx.coroutines.runBlocking
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.GraphicsMode
import java.io.File

/**
 * Turning a picked photo into what is uploaded (#241): turned upright, cut to
 * the crop, at most 2400 px, a JPEG without EXIF, in the app's cache only.
 */
@RunWith(RobolectricTestRunner::class)
@GraphicsMode(GraphicsMode.Mode.NATIVE)
class PreparePhotoTest {
    private val context = ApplicationProvider.getApplicationContext<Application>()
    private val prepare = PreparePhoto(context)
    private lateinit var work: File

    @Before
    fun setUp() {
        work = File(context.cacheDir, "test-sources").apply { mkdirs() }
    }

    /** A JPEG of [width]x[height]: the left half red, the right half blue. */
    private fun jpeg(
        width: Int,
        height: Int,
        name: String = "source.jpg",
    ): File {
        val bitmap = Bitmap.createBitmap(width, height, Bitmap.Config.ARGB_8888)
        Canvas(bitmap).apply {
            drawRect(0f, 0f, width / 2f, height.toFloat(), Paint().apply { color = Color.RED })
            drawRect(width / 2f, 0f, width.toFloat(), height.toFloat(), Paint().apply { color = Color.BLUE })
        }
        val file = File(work, name)
        file.outputStream().use { bitmap.compress(Bitmap.CompressFormat.JPEG, 95, it) }
        return file
    }

    private fun File.withExif(vararg tags: Pair<String, String>): File {
        val exif = ExifInterface(path)
        tags.forEach { (tag, value) -> exif.setAttribute(tag, value) }
        exif.saveAttributes()
        return this
    }

    private fun decode(file: File): Bitmap = BitmapFactory.decodeFile(file.path)

    private fun Int.isReddish() = Color.red(this) > 180 && Color.blue(this) < 80

    private fun Int.isBluish() = Color.blue(this) > 180 && Color.red(this) < 80

    private fun ready(result: PreparedPhoto): PreparedPhoto.Ready {
        assertTrue("expected a ready photo, got $result", result is PreparedPhoto.Ready)
        return result as PreparedPhoto.Ready
    }

    @Test
    fun aLargePhotoIsScaledToAtMost2400PixelsAsAJpeg() =
        runBlocking {
            val result = ready(prepare.prepare(jpeg(4000, 3000), PixelRect(0, 0, 4000, 3000), quarterTurns = 0))

            val options = BitmapFactory.Options().apply { inJustDecodeBounds = true }
            BitmapFactory.decodeFile(result.file.path, options)
            assertEquals(2400, options.outWidth)
            assertEquals(1800, options.outHeight)
            assertEquals("image/jpeg", options.outMimeType)
            assertEquals(2400 to 1800, result.width to result.height)
        }

    @Test
    fun aPhotoWithinTheLimitKeepsItsSize() =
        runBlocking {
            val result = ready(prepare.prepare(jpeg(800, 800), PixelRect(0, 0, 800, 800), quarterTurns = 0))

            val bitmap = decode(result.file)
            assertEquals(800, bitmap.width)
            assertEquals(800, bitmap.height)
        }

    @Test
    fun theCropIsCut() =
        runBlocking {
            // The right 1000x1000 of a 2000x1000 photo is all blue.
            val result = ready(prepare.prepare(jpeg(2000, 1000), PixelRect(1000, 0, 1000, 1000), quarterTurns = 0))

            val bitmap = decode(result.file)
            assertEquals(1000, bitmap.width)
            assertTrue(bitmap.getPixel(100, 500).isBluish())
            assertTrue(bitmap.getPixel(900, 500).isBluish())
        }

    @Test
    fun locationAndOtherExifAreDropped() =
        runBlocking {
            val source =
                jpeg(1200, 900).withExif(
                    ExifInterface.TAG_GPS_LATITUDE to "6/1,7/1,0/1",
                    ExifInterface.TAG_GPS_LATITUDE_REF to "S",
                    ExifInterface.TAG_GPS_LONGITUDE to "106/1,49/1,0/1",
                    ExifInterface.TAG_GPS_LONGITUDE_REF to "E",
                    ExifInterface.TAG_MAKE to "Kamera Uji",
                )
            assertTrue(ExifInterface(source.path).latLong != null)

            val result = ready(prepare.prepare(source, PixelRect(0, 0, 1200, 900), quarterTurns = 0))

            val exif = ExifInterface(result.file.path)
            assertNull(exif.latLong)
            assertNull(exif.getAttribute(ExifInterface.TAG_GPS_LATITUDE))
            assertNull(exif.getAttribute(ExifInterface.TAG_MAKE))
        }

    @Test
    fun aCropUnder600PixelsIsRefusedWithoutAFile() =
        runBlocking {
            val before = prepare.photoDir.listFiles().orEmpty().size

            val result = prepare.prepare(jpeg(2000, 2000), PixelRect(0, 0, 500, 500), quarterTurns = 0)

            assertEquals(PreparedPhoto.TooSmall(600), result)
            assertEquals(before, prepare.photoDir.listFiles().orEmpty().size)
        }

    @Test
    fun aPortraitPhotoFromTheCameraIsTurnedUpright() =
        runBlocking {
            // Stored landscape with "rotate 90": upright it is 800 wide and 1200 tall, the stored left (red) on top.
            val source = jpeg(1200, 800).withExif(ExifInterface.TAG_ORIENTATION to ExifInterface.ORIENTATION_ROTATE_90.toString())
            assertEquals(ImageSize(800, 1200), prepare.uprightSize(source))

            val result = ready(prepare.prepare(source, PixelRect(0, 0, 800, 800), quarterTurns = 0))

            val bitmap = decode(result.file)
            assertEquals(800, bitmap.width)
            assertEquals(800, bitmap.height)
            assertTrue(bitmap.getPixel(400, 100).isReddish())
            assertTrue(bitmap.getPixel(400, 750).isBluish())
            // The turn is in the pixels, so no orientation tag is left to apply twice.
            assertEquals(
                ExifInterface.ORIENTATION_UNDEFINED,
                ExifInterface(result.file.path).getAttributeInt(ExifInterface.TAG_ORIENTATION, ExifInterface.ORIENTATION_UNDEFINED),
            )
        }

    @Test
    fun theSellersTurnIsAppliedAfterTheCamerasOne() =
        runBlocking {
            // One quarter turn clockwise: the left (red) half ends on top.
            val result = ready(prepare.prepare(jpeg(1200, 800), PixelRect(0, 0, 800, 800), quarterTurns = 1))

            val bitmap = decode(result.file)
            assertTrue(bitmap.getPixel(400, 100).isReddish())
            assertTrue(bitmap.getPixel(400, 750).isBluish())
        }

    @Test
    fun theUploadStaysUnder5Megabytes() =
        runBlocking {
            val result = ready(prepare.prepare(jpeg(4000, 3000), PixelRect(0, 0, 4000, 3000), quarterTurns = 0))

            assertTrue(result.file.length() < 5L * 1024 * 1024)
        }

    @Test
    fun preparedPhotosStayInTheCachePhotoFolder() =
        runBlocking {
            val result = ready(prepare.prepare(jpeg(800, 800), PixelRect(0, 0, 800, 800), quarterTurns = 0))

            assertEquals(File(context.cacheDir, "photos").canonicalPath, result.file.parentFile!!.canonicalPath)
            assertTrue(result.file.name.endsWith(".jpg"))
        }

    @Test
    fun aPickedPhotoIsCopiedIntoTheCacheFirst() =
        runBlocking {
            val picked = jpeg(800, 800, name = "IMG_20261022_rumah_saya.jpg")

            val copy = prepare.import(Uri.fromFile(picked).toString())

            assertEquals(File(context.cacheDir, "photos").canonicalPath, copy!!.parentFile!!.canonicalPath)
            // The gallery's file name is not kept.
            assertTrue("rumah" !in copy.name)
            assertEquals(picked.length(), copy.length())
            assertTrue(picked.exists())
        }

    @Test
    fun somethingThatIsNotAPhotoIsUnreadable() =
        runBlocking {
            val text = File(work, "bukan-foto.jpg").apply { writeText("bukan foto") }

            assertNull(prepare.uprightSize(text))
            assertEquals(PreparedPhoto.Unreadable, prepare.prepare(text, PixelRect(0, 0, 800, 800), quarterTurns = 0))
        }
}
