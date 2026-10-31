package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.compose.foundation.layout.Box
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
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
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreField
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormState
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormTags
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config

/**
 * The store form as the board VD-Profil-Toko draws it, driven by state only.
 * MapLibre needs a real GPU, so the map is a plain box, as in OpenStoreScreenTest.
 */
@RunWith(RobolectricTestRunner::class)
@Config(qualifiers = "w411dp-h891dp")
class EditStoreScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<EditStoreEvent>()
    private var backClicks = 0

    private fun show(state: EditStoreUiState) {
        compose.setContent {
            SeafoodTheme {
                EditStoreScreen(
                    uiState = state,
                    onEvent = { events += it },
                    onUseMyLocation = {},
                    onBack = { backClicks++ },
                    map = { modifier: Modifier -> Box(modifier.testTag(FAKE_MAP)) },
                )
            }
        }
    }

    private val filled =
        EditStoreUiState(
            isLoading = false,
            form =
                StoreFormState(
                    name = "UD Kepiting Pak Budi",
                    phone = "081234567890",
                    address = "Jl. Dermaga Ujung No. 12, Muara Angke",
                    city = "Jakarta Utara",
                    pin = GeoPoint(-6.1067, 106.774),
                    bankName = "BCA",
                    bankAccountNo = "7700112233",
                    bankAccountName = "Budi Santoso",
                ),
        )

    @Test
    fun theFormShowsTheStoreAndSavesIt() {
        show(filled)

        compose.onNodeWithText(context.getString(R.string.edit_store_title)).assertIsDisplayed()
        compose.onNodeWithTag(StoreFormTags.NAME).assertTextContains("UD Kepiting Pak Budi")
        compose.onNodeWithTag(StoreFormTags.BANK_ACCOUNT_NO).performScrollTo().assertTextContains("7700112233")
        compose.onNodeWithTag(EditStoreTags.SAVE).performClick()

        assertEquals(listOf(EditStoreEvent.Save), events)
    }

    @Test
    fun typingSendsTheField() {
        show(filled)

        compose.onNodeWithTag(StoreFormTags.CITY).performScrollTo().performTextReplacement("Jakarta Barat")

        assertEquals(listOf(EditStoreEvent.FieldChanged(StoreField.City, "Jakarta Barat")), events)
    }

    @Test
    fun aSavingFormCannotBeSentAgain() {
        show(filled.copy(isSaving = true))

        compose.onNodeWithTag(EditStoreTags.SAVE).assertIsNotEnabled()
    }

    @Test
    fun aFailedSaveShowsWhy() {
        show(filled.copy(generalError = UiText.Resource(R.string.error_network)))

        compose.onNodeWithText(context.getString(R.string.error_network)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun noConnectionOnLoadingOffersToTryAgain() {
        show(EditStoreUiState(isLoading = false, trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        compose.onNodeWithTag(EditStoreTags.RETRY).performScrollTo().performClick()

        assertEquals(listOf(EditStoreEvent.Retry), events)
    }

    @Test
    fun backLeavesTheForm() {
        show(filled)

        compose.onNodeWithTag(EditStoreTags.BACK).performClick()

        assertEquals(1, backClicks)
    }

    private companion object {
        const val FAKE_MAP = "fake_map"
    }
}
