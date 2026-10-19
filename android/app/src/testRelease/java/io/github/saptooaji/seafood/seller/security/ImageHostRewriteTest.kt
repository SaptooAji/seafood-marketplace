package io.github.saptooaji.seafood.seller.security

import org.junit.Assert.assertThrows
import org.junit.Test

/**
 * The debug-only rewrite of photo hosts (#237) is not in a release build:
 * a release build loads photo URLs exactly as the server sends them.
 */
class ImageHostRewriteTest {
    @Test
    fun theDebugImageHostRewriteIsNotInARelease() {
        assertThrows(ClassNotFoundException::class.java) {
            Class.forName("io.github.saptooaji.seafood.seller.core.image.DebugImageHost")
        }
    }
}
