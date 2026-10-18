package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * The sign-in screen as the board VD-Masuk draws it, driven by state only.
 * Robolectric's screen is small, so a click scrolls to its node first.
 */
@RunWith(RobolectricTestRunner::class)
class LoginScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<LoginEvent>()
    private var registerClicks = 0

    private fun show(state: LoginUiState) {
        compose.setContent {
            SeafoodTheme {
                LoginScreen(uiState = state, onEvent = { events += it }, onRegister = { registerClicks++ })
            }
        }
    }

    @Test
    fun typingAndSubmittingSendsEvents() {
        show(LoginUiState())

        compose.onNodeWithTag(LoginTags.EMAIL).performTextInput("budi@example.com")
        compose.onNodeWithTag(LoginTags.PASSWORD).performTextInput("rahasia123")
        compose
            .onNodeWithTag(LoginTags.SUBMIT)
            .performScrollTo()
            .assertIsEnabled()
            .performClick()

        assertTrue(events.contains(LoginEvent.EmailChanged("budi@example.com")))
        assertTrue(events.contains(LoginEvent.PasswordChanged("rahasia123")))
        assertEquals(LoginEvent.Submit, events.last())
    }

    @Test
    fun theButtonIsDisabledWhileSending() {
        show(LoginUiState(email = "budi@example.com", password = "rahasia123", isSubmitting = true))

        compose.onNodeWithTag(LoginTags.SUBMIT).assertIsNotEnabled()
    }

    @Test
    fun aFieldErrorShowsUnderTheEmail() {
        show(LoginUiState(emailError = UiText.Resource(R.string.login_error_credentials)))

        compose.onNodeWithText(context.getString(R.string.login_error_credentials)).assertIsDisplayed()
    }

    @Test
    fun tooManyAttemptsShowsTheWaitAndDisablesTheButton() {
        show(LoginUiState(retryAfterSeconds = 45, generalError = UiText.Resource(R.string.auth_error_too_many, listOf(45L))))

        compose.onNodeWithText(context.getString(R.string.auth_error_too_many, 45L)).assertIsDisplayed()
        compose.onNodeWithTag(LoginTags.SUBMIT).assertIsNotEnabled()
    }

    @Test
    fun anEndedSessionIsExplained() {
        show(LoginUiState(sessionExpired = true))

        compose.onNodeWithText(context.getString(R.string.login_session_expired)).assertIsDisplayed()
    }

    @Test
    fun thePasswordCanBeShown() {
        show(LoginUiState(password = "rahasia123"))

        compose.onNodeWithText(context.getString(R.string.login_password_show)).performScrollTo().performClick()

        compose.onNodeWithText(context.getString(R.string.login_password_hide)).assertIsDisplayed()
    }

    @Test
    fun theRegisterLinkOpensRegistration() {
        show(LoginUiState())

        compose.onNodeWithTag(LoginTags.REGISTER).performScrollTo().performClick()

        assertEquals(1, registerClicks)
    }
}
