package io.github.saptooaji.seafood.seller.feature.main.ui

import androidx.compose.material3.Button
import androidx.compose.material3.Text
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsSelected
import androidx.compose.ui.test.junit4.StateRestorationTester
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The main screens: three tabs in a glass capsule, as in VD-Produk-Daftar. */
@RunWith(RobolectricTestRunner::class)
class MainShellTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private var statusClicks = 0

    @Test
    fun theProductsTabOpensFirstAndEachTabCanBeChosen() {
        compose.setContent { SeafoodTheme { MainShell(storePending = false, onStoreStatus = {}) } }

        compose.onNodeWithTag(MainTags.TAB_PRODUCTS).assertIsSelected()
        compose.onNodeWithTag(MainTags.TAB_STOCK).performClick().assertIsSelected()
        compose.onNodeWithText(context.getString(R.string.main_tab_stock)).assertIsDisplayed()
        compose.onNodeWithTag(MainTags.TAB_SETTINGS).performClick().assertIsSelected()
    }

    @Test
    fun theChosenTabSurvivesARotation() {
        val restoration = StateRestorationTester(compose)
        restoration.setContent { SeafoodTheme { MainShell(storePending = false, onStoreStatus = {}) } }

        compose.onNodeWithTag(MainTags.TAB_SETTINGS).performClick()
        restoration.emulateSavedInstanceStateRestore()

        compose.onNodeWithTag(MainTags.TAB_SETTINGS).assertIsSelected()
    }

    @Test
    fun aPendingStoreShowsTheBannerWithALinkToItsStatus() {
        compose.setContent { SeafoodTheme { MainShell(storePending = true, onStoreStatus = { statusClicks++ }) } }

        compose.onNodeWithText(context.getString(R.string.main_pending_banner)).assertIsDisplayed()
        compose.onNodeWithTag(MainTags.STORE_STATUS).performClick()

        assertEquals(1, statusClicks)
    }

    @Test
    fun theStockTabCanOpenFirstAndSendTheSellerToProducts() {
        compose.setContent {
            SeafoodTheme {
                MainShell(
                    storePending = false,
                    onStoreStatus = {},
                    initialTab = MainTab.Stock,
                    productsTab = { Text("daftar produk") },
                    stockTab = { _, openProducts -> Button(onClick = openProducts) { Text("buka produk") } },
                )
            }
        }

        compose.onNodeWithTag(MainTags.TAB_STOCK).assertIsSelected()
        compose.onNodeWithText("buka produk").performClick()

        compose.onNodeWithTag(MainTags.TAB_PRODUCTS).assertIsSelected()
        compose.onNodeWithText("daftar produk").assertIsDisplayed()
    }

    @Test
    fun anActiveStoreShowsNoBanner() {
        compose.setContent { SeafoodTheme { MainShell(storePending = false, onStoreStatus = {}) } }

        compose.onNodeWithText(context.getString(R.string.main_pending_banner)).assertDoesNotExist()
    }
}
