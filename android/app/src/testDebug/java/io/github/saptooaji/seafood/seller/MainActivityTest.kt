package io.github.saptooaji.seafood.seller

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.v2.createAndroidComposeRule
import androidx.compose.ui.test.onAllNodesWithTag
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performScrollTo
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginTags
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The real app, with its Hilt graph, on a device with no stored token. */
@RunWith(RobolectricTestRunner::class)
class MainActivityTest {
    @get:Rule
    val compose = createAndroidComposeRule<MainActivity>()

    @Test
    fun withoutATokenTheAppOpensOnSignIn() {
        compose.waitUntil(timeoutMillis = 5_000) {
            compose.onAllNodesWithTag(LoginTags.EMAIL).fetchSemanticsNodes().isNotEmpty()
        }

        // The Google button (when local.properties has a client ID) pushes the
        // field below Robolectric's small screen.
        compose.onNodeWithTag(LoginTags.EMAIL).performScrollTo().assertIsDisplayed()
    }
}
