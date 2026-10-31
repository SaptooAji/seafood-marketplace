package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.assertIsOff
import androidx.compose.ui.test.assertIsOn
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OJOL
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_PER_KM
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.PICKUP
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/** Cara terima pesanan as the board VD-Metode-Pengiriman and its states draw it, driven by state only. */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class ShippingMethodsScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<ShippingMethodsEvent>()
    private var ownDeliveryClicks = 0
    private var backClicks = 0

    private fun show(state: ShippingMethodsUiState) {
        compose.setContent {
            SeafoodTheme {
                ShippingMethodsScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onOwnDelivery = { ownDeliveryClicks++ },
                    onBack = { backClicks++ },
                )
            }
        }
    }

    private fun state(
        vararg methods: ShippingMethod,
        isOwner: Boolean = true,
    ) = ShippingMethodsUiState(
        isLoading = false,
        isOwner = isOwner,
        rows = ShippingType.entries.map { type -> ShippingRow(type, methods.firstOrNull { it.type == type }) },
    )

    @Test
    fun theOwnerSeesEveryKindWithItsSummarySwitchOrAddButton() {
        show(state(PICKUP, OWN_PER_KM))

        compose.onNodeWithText(context.getString(R.string.shipping_title)).assertIsDisplayed()
        compose
            .onNodeWithTag(ShippingTags.row(ShippingType.OwnDelivery))
            .performScrollTo()
            .assertTextContains(context.getString(R.string.shipping_summary_per_km, "Rp3.000", "Rp10.000", "20"))
        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.Pickup)).assertIsOn()
        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.OwnDelivery)).performScrollTo().assertIsOn()
        compose.onNodeWithTag(ShippingTags.EDIT_OWN_DELIVERY).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.add(ShippingType.Ojol)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.add(ShippingType.Expedition)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.Ojol)).assertDoesNotExist()
    }

    @Test
    fun anAddedOjolHasASwitchAndNoEditButton() {
        show(state(PICKUP, OWN_PER_KM, OJOL.copy(isActive = false)))

        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.Ojol)).performScrollTo().assertIsOff()
        compose.onNodeWithTag(ShippingTags.add(ShippingType.Ojol)).assertDoesNotExist()
    }

    @Test
    fun flippingASwitchSendsTheNewState() {
        show(state(PICKUP, OWN_PER_KM))

        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.Pickup)).performClick()

        assertEquals(listOf(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false)), events)
    }

    @Test
    fun ownDeliveryOpensItsFormToEditOrToAdd() {
        show(state(PICKUP, OWN_PER_KM))
        compose.onNodeWithTag(ShippingTags.EDIT_OWN_DELIVERY).performScrollTo().performClick()

        assertEquals(1, ownDeliveryClicks)
        assertEquals(emptyList<ShippingMethodsEvent>(), events)
    }

    @Test
    fun anOlderStoreWithoutOwnDeliveryCanAddIt() {
        show(state(PICKUP))

        compose.onNodeWithTag(ShippingTags.add(ShippingType.OwnDelivery)).performScrollTo().performClick()

        assertEquals(1, ownDeliveryClicks)
    }

    @Test
    fun addingOjolAsksInASheet() {
        show(state(PICKUP, OWN_PER_KM))

        compose.onNodeWithTag(ShippingTags.add(ShippingType.Ojol)).performScrollTo().performClick()

        assertEquals(listOf(ShippingMethodsEvent.Add(ShippingType.Ojol)), events)
    }

    @Test
    fun theSheetAddsOrCancels() {
        show(state(PICKUP, OWN_PER_KM).copy(adding = ShippingType.Ojol))

        compose.onNodeWithText(context.getString(R.string.shipping_add_ojol_title), useUnmergedTree = true).assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.CANCEL_ADD).performClick()
        compose.onNodeWithTag(ShippingTags.CONFIRM_ADD).performClick()

        assertEquals(listOf(ShippingMethodsEvent.CloseAdd, ShippingMethodsEvent.ConfirmAdd), events)
    }

    @Test
    fun aSheetThatIsAddingCannotBeSentAgain() {
        show(state(PICKUP, OWN_PER_KM).copy(adding = ShippingType.Expedition, isAdding = true))

        compose.onNodeWithText(context.getString(R.string.shipping_add_expedition_title), useUnmergedTree = true).assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.CONFIRM_ADD).assertIsNotEnabled()
    }

    @Test
    fun staffSeeTheListWithoutAnyWayToChangeIt() {
        show(state(PICKUP, OWN_PER_KM, isOwner = false))

        compose.onNodeWithTag(ShippingTags.toggle(ShippingType.Pickup)).assertIsNotEnabled()
        compose.onNodeWithTag(ShippingTags.EDIT_OWN_DELIVERY).assertDoesNotExist()
        compose.onNodeWithTag(ShippingTags.add(ShippingType.Ojol)).assertDoesNotExist()
        compose.onNodeWithText(context.getString(R.string.shipping_owner_only_hint)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theServersRefusalIsShownAsIs() {
        show(state(PICKUP).copy(notice = ShippingNotice.Refused("Toko harus punya minimal satu cara kirim yang aktif.")))

        compose.onNodeWithText("Toko harus punya minimal satu cara kirim yang aktif.").assertIsDisplayed()
    }

    @Test
    fun aStaleOwnerViewIsToldOnlyTheOwnerMayChangeIt() {
        show(state(PICKUP, OWN_PER_KM, isOwner = false).copy(notice = ShippingNotice.OwnerOnly))

        compose.onNodeWithText(context.getString(R.string.shipping_owner_only)).assertIsDisplayed()
    }

    @Test
    fun noConnectionOffersToTryAgain() {
        show(ShippingMethodsUiState(isLoading = false, trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        compose.onNodeWithTag(ShippingTags.RETRY).performScrollTo().performClick()

        assertEquals(listOf(ShippingMethodsEvent.Retry), events)
    }

    @Test
    fun backLeaves() {
        show(state(PICKUP, OWN_PER_KM))

        compose.onNodeWithTag(ShippingTags.BACK).performClick()

        assertEquals(1, backClicks)
    }
}
