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
 * The registration screen as the board VD-Daftar draws it, driven by state
 * only. The button and the sign-in link sit in a bar fixed to the bottom; the
 * fields scroll, and Robolectric's screen is small, so a field's text is
 * scrolled to before it is checked.
 */
@RunWith(RobolectricTestRunner::class)
class RegisterScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<RegisterEvent>()
    private var backClicks = 0
    private var loginClicks = 0

    private fun show(state: RegisterUiState) {
        compose.setContent {
            SeafoodTheme {
                RegisterScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onBack = { backClicks++ },
                    onLogin = { loginClicks++ },
                )
            }
        }
    }

    @Test
    fun typingInEveryFieldAndSubmittingSendsEvents() {
        show(RegisterUiState())

        compose.onNodeWithTag(RegisterTags.NAME).performTextInput("Budi Santoso")
        compose.onNodeWithTag(RegisterTags.EMAIL).performTextInput("budi@example.com")
        compose.onNodeWithTag(RegisterTags.PHONE).performTextInput("0812 3456 7890")
        compose.onNodeWithTag(RegisterTags.PASSWORD).performTextInput("rahasia123")
        compose.onNodeWithTag(RegisterTags.PASSWORD_CONFIRMATION).performTextInput("rahasia123")
        compose
            .onNodeWithTag(RegisterTags.SUBMIT)
            .assertIsEnabled()
            .performClick()

        assertTrue(events.contains(RegisterEvent.NameChanged("Budi Santoso")))
        assertTrue(events.contains(RegisterEvent.EmailChanged("budi@example.com")))
        assertTrue(events.contains(RegisterEvent.PhoneChanged("0812 3456 7890")))
        assertTrue(events.contains(RegisterEvent.PasswordChanged("rahasia123")))
        assertTrue(events.contains(RegisterEvent.PasswordConfirmationChanged("rahasia123")))
        assertEquals(RegisterEvent.Submit, events.last())
    }

    @Test
    fun theHelpTextsExplainThePhoneAndPasswordRules() {
        show(RegisterUiState())

        compose.onNodeWithText(context.getString(R.string.register_phone_help)).assertExists()
        compose.onNodeWithText(context.getString(R.string.register_password_help)).assertExists()
    }

    @Test
    fun anEmailInUseShowsUnderTheEmail() {
        show(RegisterUiState(emailError = UiText.Resource(R.string.register_error_email_taken)))

        compose.onNodeWithText(context.getString(R.string.register_error_email_taken)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aMismatchShowsUnderTheConfirmation() {
        show(RegisterUiState(passwordConfirmationError = UiText.Resource(R.string.register_error_password_mismatch)))

        compose.onNodeWithText(context.getString(R.string.register_error_password_mismatch)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theButtonIsDisabledWhileSending() {
        show(RegisterUiState(isSubmitting = true))

        compose.onNodeWithTag(RegisterTags.SUBMIT).assertIsNotEnabled()
    }

    @Test
    fun tooManyAttemptsShowsTheWaitAndDisablesTheButton() {
        show(RegisterUiState(retryAfterSeconds = 30, generalError = UiText.Resource(R.string.auth_error_too_many, listOf(30L))))

        compose.onNodeWithText(context.getString(R.string.auth_error_too_many, 30L)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(RegisterTags.SUBMIT).assertIsNotEnabled()
    }

    @Test
    fun backAndTheSignInLinkLeaveTheScreen() {
        show(RegisterUiState())

        compose.onNodeWithTag(RegisterTags.BACK).performClick()
        compose.onNodeWithTag(RegisterTags.LOGIN).performClick()

        assertEquals(1, backClicks)
        assertEquals(1, loginClicks)
    }
}
