package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.compose.ui.test.assertIsDisplayed
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
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_PER_KM
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/** The Antar sendiri form as the boards VD-Antar-Sendiri and its states draw it, driven by state only. */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class OwnDeliveryScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<OwnDeliveryEvent>()
    private var backClicks = 0

    private fun show(state: OwnDeliveryUiState) {
        compose.setContent {
            SeafoodTheme {
                OwnDeliveryScreen(uiState = state, onEvent = { events += it }, onBack = { backClicks++ })
            }
        }
    }

    private val perKm = OwnDeliveryUiState(isLoading = false, isNew = false, form = OwnDeliveryForm.from(OWN_PER_KM))
    private val newFlat =
        OwnDeliveryUiState(
            isLoading = false,
            isNew = true,
            form = OwnDeliveryForm(radius = "10", feeType = ShippingFeeType.Flat, flatFee = "10000"),
        )

    @Test
    fun aNewMethodStartsFlatWithoutTheActiveSwitch() {
        show(newFlat)

        compose.onNodeWithText(context.getString(R.string.shipping_own_delivery_title)).assertIsDisplayed()
        compose.onNodeWithTag(OwnDeliveryTags.FLAT_FEE).performScrollTo().assertTextContains("10000")
        compose.onNodeWithTag(OwnDeliveryTags.FEE_PER_KM).assertDoesNotExist()
        compose.onNodeWithTag(OwnDeliveryTags.ACTIVE).assertDoesNotExist()
        compose.onNodeWithTag(OwnDeliveryTags.SAVE).assertTextContains(context.getString(R.string.shipping_add))
    }

    @Test
    fun anExistingPerKmMethodShowsBothFeesAndTheActiveSwitch() {
        show(perKm)

        compose.onNodeWithTag(OwnDeliveryTags.RADIUS).assertTextContains("20")
        compose.onNodeWithTag(OwnDeliveryTags.FEE_PER_KM).performScrollTo().assertTextContains("3000")
        compose.onNodeWithTag(OwnDeliveryTags.MIN_FEE).performScrollTo().assertTextContains("10000")
        compose.onNodeWithTag(OwnDeliveryTags.FLAT_FEE).assertDoesNotExist()
        compose.onNodeWithTag(OwnDeliveryTags.ACTIVE).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(OwnDeliveryTags.SAVE).assertTextContains(context.getString(R.string.settings_save))
    }

    @Test
    fun typingPickingAndSavingSendEvents() {
        show(newFlat)

        compose.onNodeWithTag(OwnDeliveryTags.RADIUS).performTextReplacement("7,5")
        compose.onNodeWithTag(OwnDeliveryTags.CHOOSE_PER_KM).performScrollTo().performClick()
        compose.onNodeWithTag(OwnDeliveryTags.SAVE).performClick()

        assertEquals(OwnDeliveryEvent.FieldChanged(OwnDeliveryField.Radius, "7,5"), events.first())
        assertEquals(OwnDeliveryEvent.FeeTypePicked(ShippingFeeType.PerKm), events[events.size - 2])
        assertEquals(OwnDeliveryEvent.Save, events.last())
    }

    @Test
    fun theActiveSwitchSendsTheNewState() {
        show(perKm)

        compose.onNodeWithTag(OwnDeliveryTags.ACTIVE).performScrollTo().performClick()

        assertEquals(listOf(OwnDeliveryEvent.ActiveChanged(false)), events)
    }

    @Test
    fun thePreviewShowsWhatBuyersPay() {
        show(perKm)

        compose.onNodeWithText("Rp18.000").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("Rp45.000").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.shipping_preview_unavailable)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun anEmptyRadiusShowsItsError() {
        show(
            newFlat.copy(
                form =
                    newFlat.form.copy(
                        radius = "",
                        errors =
                            mapOf(
                                OwnDeliveryField.Radius to UiText.Resource(R.string.shipping_error_radius),
                            ),
                    ),
            ),
        )

        compose.onNodeWithText(context.getString(R.string.shipping_error_radius)).assertIsDisplayed()
    }

    @Test
    fun theServersRefusalOfTheSwitchShowsUnderIt() {
        show(
            perKm.copy(
                form =
                    perKm.form.copy(
                        errors =
                            mapOf(
                                OwnDeliveryField.Active to UiText.Raw("Toko harus punya minimal satu cara kirim yang aktif."),
                            ),
                    ),
            ),
        )

        compose.onNodeWithText("Toko harus punya minimal satu cara kirim yang aktif.").performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aSavingFormCannotBeSentAgain() {
        show(perKm.copy(isSaving = true))

        compose.onNodeWithTag(OwnDeliveryTags.SAVE).assertIsNotEnabled()
    }

    @Test
    fun aFailedSaveShowsWhy() {
        show(perKm.copy(generalError = UiText.Resource(R.string.error_network)))

        compose.onNodeWithText(context.getString(R.string.error_network)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun noConnectionOnLoadingOffersToTryAgain() {
        show(OwnDeliveryUiState(isLoading = false, trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        compose.onNodeWithTag(OwnDeliveryTags.RETRY).performScrollTo().performClick()

        assertEquals(listOf(OwnDeliveryEvent.Retry), events)
    }

    @Test
    fun backLeaves() {
        show(perKm)

        compose.onNodeWithTag(OwnDeliveryTags.BACK).performClick()

        assertEquals(1, backClicks)
    }
}
