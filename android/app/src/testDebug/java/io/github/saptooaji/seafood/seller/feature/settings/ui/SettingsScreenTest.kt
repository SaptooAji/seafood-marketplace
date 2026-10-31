package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsEnabled
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextReplacement
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import java.time.LocalTime

/** Pengaturan as the boards VD-Pengaturan and VD-Ubah-Profil draw it, driven by state only. */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class SettingsScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<SettingsEvent>()
    private var editStoreClicks = 0
    private var shippingClicks = 0

    private fun show(state: SettingsUiState) {
        compose.setContent {
            SeafoodTheme {
                SettingsScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onEditStore = { editStoreClicks++ },
                    onShippingMethods = { shippingClicks++ },
                    versionName = "0.4.0",
                )
            }
        }
    }

    private val owner =
        SettingsUiState(isLoading = false, user = FakeAuthRepository.BUDI, store = FakeStoreRepository.OWNED_STORE)

    private val staff = owner.copy(store = FakeStoreRepository.STAFF_STORE)

    // --- What the tab shows ---------------------------------------------------

    @Test
    fun theOwnerSeesTheProfileTheStoreTheBankAccountAndTheReminder() {
        show(owner)

        compose.onNodeWithText("Budi Santoso").assertIsDisplayed()
        compose.onNodeWithText("budi@example.com").assertIsDisplayed()
        compose.onNodeWithText("081234567890").assertIsDisplayed()
        compose.onNodeWithText("UD Kepiting Pak Budi").assertIsDisplayed()
        compose.onNodeWithText("Aktif").assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.BANK).performScrollTo().assertTextContains("BCA •••2233, Budi Santoso")
        compose.onNodeWithTag(SettingsTags.REMINDER).performScrollTo().assertTextContains("06.00")
        compose.onNodeWithText("Versi 0.4.0").performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theWholeAccountNumberIsNeverShown() {
        show(owner)

        compose.onNodeWithText("7700112233", substring = true).assertDoesNotExist()
    }

    @Test
    fun aUserWithoutAnAvatarShowsTheFirstLetterOfTheName() {
        show(owner)

        compose.onNodeWithText("B").assertIsDisplayed()
    }

    @Test
    fun staffSeeNoBankAccountAndCannotEditTheStoreOrTheReminder() {
        show(staff)

        compose.onNodeWithTag(SettingsTags.BANK).assertDoesNotExist()
        compose.onNodeWithTag(SettingsTags.EDIT_STORE).assertDoesNotExist()
        compose.onNodeWithTag(SettingsTags.SHIPPING).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.REMINDER).performScrollTo().assertIsNotEnabled()
        compose.onNodeWithText(context.getString(R.string.settings_owner_only_hint)).performScrollTo().assertIsDisplayed()
        // Staff still edit their own profile.
        compose.onNodeWithTag(SettingsTags.EDIT_PROFILE).assertIsEnabled()
    }

    @Test
    fun aPendingStoreShowsTheServersLabel() {
        show(owner.copy(store = FakeStoreRepository.PENDING_STORE))

        compose.onNodeWithText("Menunggu Persetujuan").assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.EDIT_STORE).performScrollTo().assertIsEnabled()
    }

    @Test
    fun aStoreWithoutAReminderSaysSo() {
        show(owner.copy(store = FakeStoreRepository.ACTIVE_STORE))

        compose
            .onNodeWithTag(SettingsTags.REMINDER)
            .performScrollTo()
            .assertTextContains(context.getString(R.string.settings_reminder_unset))
    }

    @Test
    fun theRowsOpenTheirScreens() {
        show(owner)

        compose.onNodeWithTag(SettingsTags.EDIT_PROFILE).performClick()
        compose.onNodeWithTag(SettingsTags.EDIT_STORE).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.BANK).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.SHIPPING).performScrollTo().performClick()

        assertEquals(listOf(SettingsEvent.EditProfile), events)
        assertEquals(2, editStoreClicks)
        assertEquals(1, shippingClicks)
    }

    @Test
    fun aNoticeThatOnlyTheOwnerMayChangeTheStoreIsShown() {
        show(staff.copy(notice = SettingsNotice.OwnerOnly))

        compose.onNodeWithText(context.getString(R.string.settings_owner_only)).assertIsDisplayed()
    }

    // --- Signing out ----------------------------------------------------------

    @Test
    fun signingOutAsksFirst() {
        show(owner)

        compose.onNodeWithTag(SettingsTags.SIGN_OUT).performScrollTo().performClick()

        assertEquals(listOf(SettingsEvent.AskSignOut), events)
    }

    @Test
    fun theConfirmationSignsOutOrCancels() {
        show(owner.copy(confirmingSignOut = true))

        compose.onNodeWithText(context.getString(R.string.settings_sign_out_title)).assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.SIGN_OUT_CANCEL).performClick()
        compose.onNodeWithTag(SettingsTags.SIGN_OUT_CONFIRM).performClick()

        assertEquals(listOf(SettingsEvent.CancelSignOut, SettingsEvent.SignOut), events)
    }

    @Test
    fun noConnectionOffersToTryAgainAndStillToSignOut() {
        show(SettingsUiState(isLoading = false, trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.RETRY).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.SIGN_OUT).performScrollTo().performClick()

        assertEquals(listOf(SettingsEvent.Retry, SettingsEvent.AskSignOut), events)
    }

    // --- Stock reminder sheet -------------------------------------------------

    @Test
    fun theReminderSheetSavesTheTypedTime() {
        show(owner)

        compose.onNodeWithTag(SettingsTags.REMINDER).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.REMINDER_HOUR, useUnmergedTree = true).performTextReplacement("06")
        compose.onNodeWithTag(SettingsTags.REMINDER_MINUTE, useUnmergedTree = true).performTextReplacement("30")
        compose.onNodeWithTag(SettingsTags.REMINDER_SAVE, useUnmergedTree = true).performClick()

        assertEquals(listOf(SettingsEvent.SaveReminder(LocalTime.of(6, 30))), events)
    }

    @Test
    fun theReminderSheetStartsAtTheCurrentTimeAndCanRemoveIt() {
        show(owner)

        compose.onNodeWithTag(SettingsTags.REMINDER).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.REMINDER_HOUR, useUnmergedTree = true).assertTextContains("06")
        compose.onNodeWithTag(SettingsTags.REMINDER_MINUTE, useUnmergedTree = true).assertTextContains("00")
        compose
            .onNodeWithText(context.getString(R.string.settings_reminder_help), useUnmergedTree = true)
            .assertIsDisplayed()
        compose.onNodeWithTag(SettingsTags.REMINDER_REMOVE, useUnmergedTree = true).performClick()

        assertEquals(listOf(SettingsEvent.SaveReminder(null)), events)
    }

    @Test
    fun aTimeOffTheClockCannotBeSaved() {
        show(owner)

        compose.onNodeWithTag(SettingsTags.REMINDER).performScrollTo().performClick()
        compose.onNodeWithTag(SettingsTags.REMINDER_HOUR, useUnmergedTree = true).performTextReplacement("25")

        compose.onNodeWithTag(SettingsTags.REMINDER_SAVE, useUnmergedTree = true).assertIsNotEnabled()
    }

    // --- Profile sheet --------------------------------------------------------

    @Test
    fun theProfileSheetEditsTheNameAndPhoneButOnlyShowsTheEmail() {
        show(owner.copy(profile = ProfileFormState(name = "Budi Santoso", phone = "081234567890")))

        compose.onNodeWithTag(SettingsTags.PROFILE_NAME, useUnmergedTree = true).performTextReplacement("Budi S.")
        compose.onNodeWithTag(SettingsTags.PROFILE_PHONE, useUnmergedTree = true).performTextReplacement("081299998888")
        compose.onNodeWithTag(SettingsTags.PROFILE_EMAIL, useUnmergedTree = true).assertTextContains("budi@example.com")
        compose.onNodeWithTag(SettingsTags.PROFILE_EMAIL, useUnmergedTree = true).assertIsNotEnabled()
        compose.onNodeWithTag(SettingsTags.PROFILE_SAVE, useUnmergedTree = true).performClick()

        // The state here never changes, so a field may send its old value again when focus moves.
        assertTrue(SettingsEvent.ProfileChanged(ProfileField.Name, "Budi S.") in events)
        assertTrue(SettingsEvent.ProfileChanged(ProfileField.Phone, "081299998888") in events)
        assertEquals(SettingsEvent.SaveProfile, events.last())
    }

    @Test
    fun aRefusedPhoneShowsUnderThePhoneField() {
        show(
            owner.copy(
                profile =
                    ProfileFormState(
                        name = "Budi Santoso",
                        phone = "12345",
                        errors = mapOf(ProfileField.Phone to UiText.Resource(R.string.register_error_phone_invalid)),
                    ),
            ),
        )

        compose
            .onNodeWithText(context.getString(R.string.register_error_phone_invalid), useUnmergedTree = true)
            .assertIsDisplayed()
    }

    @Test
    fun aSavingProfileCannotBeSentAgain() {
        show(owner.copy(profile = ProfileFormState(name = "Budi Santoso", phone = "081234567890", isSaving = true)))

        compose.onNodeWithTag(SettingsTags.PROFILE_SAVE, useUnmergedTree = true).assertIsNotEnabled()
    }
}
