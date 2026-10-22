package io.github.saptooaji.seafood.seller.feature.product.photo

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

/**
 * The crop of the crop screen (#241, board VD-Potong-Foto) in the photo's own
 * pixels: the frame keeps its aspect, the photo always fills it.
 */
class CropStateTest {
    @Test
    fun `a square starts as the largest centred square`() {
        assertEquals(PixelRect(left = 500, top = 0, width = 3000, height = 3000), CropState.of(4000, 3000).rect())
    }

    @Test
    fun `4 to 3 on a landscape photo of that shape takes all of it`() {
        assertEquals(PixelRect(0, 0, 4000, 3000), CropState.of(4000, 3000).withAspect(CropAspect.Wide).rect())
    }

    @Test
    fun `4 to 3 on a portrait photo is a centred band`() {
        assertEquals(PixelRect(0, 875, 3000, 2250), CropState.of(3000, 4000).withAspect(CropAspect.Wide).rect())
    }

    @Test
    fun `zooming in keeps the centre and shrinks the crop`() {
        assertEquals(PixelRect(1250, 750, 1500, 1500), CropState.of(4000, 3000).zoomedBy(2f).rect())
    }

    @Test
    fun `zooming out stops at the whole frame`() {
        val state = CropState.of(4000, 3000).zoomedBy(0.25f)

        assertEquals(1f, state.zoom)
        assertEquals(PixelRect(500, 0, 3000, 3000), state.rect())
    }

    @Test
    fun `panning stops at the edges of the photo`() {
        val zoomed = CropState.of(4000, 3000).zoomedBy(2f)

        assertEquals(PixelRect(2500, 0, 1500, 1500), zoomed.pannedBy(10_000f, -10_000f).rect())
        assertEquals(PixelRect(0, 1500, 1500, 1500), zoomed.pannedBy(-10_000f, 10_000f).rect())
    }

    @Test
    fun `panning a square across a wide photo moves only sideways`() {
        assertEquals(PixelRect(200, 0, 3000, 3000), CropState.of(4000, 3000).pannedBy(-300f, 400f).rect())
    }

    @Test
    fun `turning swaps the sides, keeps the aspect, and starts the crop over`() {
        val turned = CropState.of(4000, 3000).withAspect(CropAspect.Wide).zoomedBy(2f).rotated()

        assertEquals(1, turned.quarterTurns)
        assertEquals(3000, turned.turnedWidth)
        assertEquals(4000, turned.turnedHeight)
        assertEquals(CropAspect.Wide, turned.aspect)
        assertEquals(1f, turned.zoom)
        assertEquals(PixelRect(0, 875, 3000, 2250), turned.rect())
    }

    @Test
    fun `four turns come back to the start`() {
        val state = CropState.of(4000, 3000)

        assertEquals(state, state.rotated().rotated().rotated().rotated())
    }

    @Test
    fun `changing the aspect starts the crop over`() {
        val state = CropState.of(4000, 3000).zoomedBy(3f).pannedBy(500f, 0f).withAspect(CropAspect.Wide)

        assertEquals(1f, state.zoom)
        assertEquals(PixelRect(0, 0, 4000, 3000), state.rect())
    }

    @Test
    fun `a crop with a short side under 600 pixels is too small`() {
        assertTrue(CropState.of(800, 500).isTooSmall())
        assertFalse(CropState.of(600, 600).isTooSmall())
        // 4000x3000 zoomed 6 times is a 500 px square.
        assertTrue(CropState.of(4000, 3000).zoomedBy(6f).isTooSmall())
    }

    @Test
    fun `the minimum is the server's photo_min_side`() {
        assertEquals(600, PHOTO_MIN_SIDE)
    }
}
