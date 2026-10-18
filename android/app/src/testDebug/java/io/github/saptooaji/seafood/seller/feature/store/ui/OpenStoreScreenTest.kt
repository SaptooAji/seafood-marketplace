package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
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
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * The open-store screen as the board VD-Buka-Toko draws it, driven by state
 * only. MapLibre needs a real GPU, so the map itself is replaced by a plain
 * box; the pin, the attribution, and the location button around it are real.
 */
@RunWith(RobolectricTestRunner::class)
class OpenStoreScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<OpenStoreEvent>()
    private var locationClicks = 0
    private var statusClicks = 0

    private fun show(state: OpenStoreUiState) {
        compose.setContent {
            SeafoodTheme {
                OpenStoreScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onUseMyLocation = { locationClicks++ },
                    onStoreStatus = { statusClicks++ },
                    map = { modifier -> Box(modifier.testTag(FAKE_MAP)) },
                )
            }
        }
    }

    @Test
    fun typingInEveryFieldAndSubmittingSendsEvents() {
        show(OpenStoreUiState())

        val typed =
            listOf(
                StoreFormTags.NAME to "UD Kepiting Pak Budi",
                StoreFormTags.DESCRIPTION to "Pengepul kepiting bakau",
                StoreFormTags.PHONE to "0812 3456 7890",
                StoreFormTags.ADDRESS to "Jl. Dermaga Ujung No. 12",
                StoreFormTags.CITY to "Jakarta Utara",
                StoreFormTags.BANK_NAME to "BCA",
                StoreFormTags.BANK_ACCOUNT_NO to "1234567890",
                StoreFormTags.BANK_ACCOUNT_NAME to "Budi Santoso",
            )
        for ((tag, text) in typed) {
            compose.onNodeWithTag(tag).performScrollTo().performTextInput(text)
        }
        compose
            .onNodeWithTag(OpenStoreTags.SUBMIT)
            .assertIsEnabled()
            .performClick()

        val fields = StoreField.entries.filter { it != StoreField.Pin }
        val changed = events.filterIsInstance<OpenStoreEvent.FieldChanged>()
        assertEquals(fields.zip(typed.map { it.second }).toSet(), changed.map { it.field to it.value }.toSet())
        assertEquals(OpenStoreEvent.Submit, events.last())
    }

    @Test
    fun theMapShowsThePinTheAttributionAndTheLocationButton() {
        show(OpenStoreUiState())

        compose.onNodeWithTag(FAKE_MAP).performScrollTo()
        compose.onNodeWithTag(StoreFormTags.PIN).assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.store_map_attribution)).assertIsDisplayed()
        compose.onNodeWithTag(StoreFormTags.MY_LOCATION).assertIsDisplayed().performClick()

        assertEquals(1, locationClicks)
    }

    @Test
    fun thePinCoordinatesAreWrittenTheIndonesianWay() {
        show(OpenStoreUiState(form = StoreFormState(pin = GeoPoint(-6.1067, 106.7740))))

        compose.onNodeWithText("Titik: −6,1067000, 106,7740000").performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aMissingPinSaysSo() {
        show(
            OpenStoreUiState(
                form = StoreFormState(errors = mapOf(StoreField.Pin to UiText.Resource(R.string.store_error_pin_required))),
            ),
        )

        compose.onNodeWithText(context.getString(R.string.store_pin_unset)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.store_error_pin_required)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aDeniedPermissionExplainsHowToPlaceThePin() {
        show(OpenStoreUiState(locationMessage = UiText.Resource(R.string.store_location_denied)))

        compose.onNodeWithText(context.getString(R.string.store_location_denied)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aWrongAccountNumberShowsUnderTheAccountField() {
        show(
            OpenStoreUiState(
                form =
                    StoreFormState(
                        bankAccountNo = "12ab",
                        errors = mapOf(StoreField.BankAccountNo to UiText.Resource(R.string.store_error_bank_account_no_invalid)),
                    ),
            ),
        )

        compose.onNodeWithText(context.getString(R.string.store_error_bank_account_no_invalid)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theAccountSectionSaysWhoSeesItAndTheRule() {
        show(OpenStoreUiState())

        compose.onNodeWithText(context.getString(R.string.store_bank_help)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.store_bank_account_no_help)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aSellerWhoAlreadyHasAStoreCanOpenItsStatus() {
        show(OpenStoreUiState(alreadyHasStore = true))

        compose.onNodeWithText(context.getString(R.string.open_store_already_has_store), substring = true).assertIsDisplayed()
        compose.onNodeWithTag(OpenStoreTags.STORE_STATUS).performClick()

        assertEquals(1, statusClicks)
    }

    @Test
    fun aGeneralErrorShowsAboveTheForm() {
        show(OpenStoreUiState(generalError = UiText.Resource(R.string.error_network)))

        compose.onNodeWithText(context.getString(R.string.error_network)).assertIsDisplayed()
    }

    @Test
    fun theButtonIsDisabledWhileSending() {
        show(OpenStoreUiState(isSubmitting = true))

        compose
            .onNodeWithTag(OpenStoreTags.SUBMIT)
            .assertIsNotEnabled()
        compose.onNodeWithText(context.getString(R.string.open_store_submitting)).assertExists()
        assertTrue(events.isEmpty())
    }

    private companion object {
        const val FAKE_MAP = "fake_map"
    }
}
