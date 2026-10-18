package io.github.saptooaji.seafood.seller.feature.welcome.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.junit4.v2.createAndroidComposeRule
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithText
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.MainActivity
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * Compose UI tests on the JVM with Robolectric. They live in `testDebug`
 * because the Compose test activity comes from `ui-test-manifest`, which only
 * the debug build includes.
 */
@RunWith(RobolectricTestRunner::class)
class WelcomeScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()

    @Test
    fun showsTheAppNameAndTagline() {
        compose.setContent { SeafoodTheme { WelcomeScreen() } }

        compose.onNodeWithText(context.getString(R.string.app_name)).assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.welcome_tagline)).assertIsDisplayed()
    }
}

@RunWith(RobolectricTestRunner::class)
class MainActivityTest {
    @get:Rule
    val compose = createAndroidComposeRule<MainActivity>()

    @Test
    fun opensOnTheWelcomeScreen() {
        compose.onNodeWithText(compose.activity.getString(R.string.app_name)).assertIsDisplayed()
    }
}
