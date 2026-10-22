package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertIsNotEnabled
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/** The store status screens as the board VD-Status-Toko draws them, driven by state only. */
@RunWith(RobolectricTestRunner::class)
class StoreStatusScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<StoreStatusEvent>()
    private var startProductsClicks = 0

    private fun show(state: StoreStatusUiState) {
        compose.setContent {
            SeafoodTheme {
                StoreStatusScreen(uiState = state, onEvent = { events += it }, onStartProducts = { startProductsClicks++ })
            }
        }
    }

    private fun store(
        status: StoreStatus,
        label: String,
        reason: String? = null,
    ) = StoreStatusUiState(
        isLoading = false,
        store = StoreStatusInfo(name = "UD Kepiting Pak Budi", status = status, statusLabel = label, rejectionReason = reason),
    )

    @Test
    fun aPendingStoreCanStartOnProductsCheckAgainOrSignOut() {
        show(store(StoreStatus.Pending, "Menunggu Persetujuan"))

        compose.onNodeWithText(context.getString(R.string.store_status_pending_title)).assertIsDisplayed()
        compose.onNodeWithText("Menunggu Persetujuan").performScrollTo().assertIsDisplayed()
        // "Diperiksa admin" is both the sign and the current step; the step says how long it takes.
        compose.onNodeWithText(context.getString(R.string.store_status_step_review_time)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(StoreStatusTags.START_PRODUCTS).performScrollTo().performClick()
        compose.onNodeWithTag(StoreStatusTags.CHECK_AGAIN).performScrollTo().performClick()
        compose.onNodeWithTag(StoreStatusTags.SIGN_OUT).performScrollTo().performClick()

        assertEquals(1, startProductsClicks)
        assertEquals(listOf(StoreStatusEvent.CheckAgain, StoreStatusEvent.SignOut), events)
    }

    @Test
    fun aRejectedStoreShowsTheReasonAsPlainText() {
        show(store(StoreStatus.Rejected, "Ditolak", reason = "Lihat <b>foto</b> di https://contoh.test"))

        compose.onNodeWithText(context.getString(R.string.store_status_rejected_title)).assertIsDisplayed()
        // Written by an admin: shown as typed, with no markup or link turned on.
        compose.onNodeWithText("Lihat <b>foto</b> di https://contoh.test").performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(StoreStatusTags.CHECK_AGAIN).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(StoreStatusTags.SIGN_OUT).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aLongReasonScrollsToItsEnd() {
        val reason = "Data toko perlu diperbaiki. ".repeat(70).trim() + " Akhir catatan."
        show(store(StoreStatus.Rejected, "Ditolak", reason = reason))

        compose.onNodeWithText(reason).performScrollTo()
        compose.onNodeWithTag(StoreStatusTags.SIGN_OUT).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aSuspendedStoreShowsTheServersMessage() {
        show(
            store(StoreStatus.Suspended, "Ditangguhkan").copy(
                serverMessage = "Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.",
            ),
        )

        compose.onNodeWithText(context.getString(R.string.store_status_suspended_title)).assertIsDisplayed()
        compose
            .onNodeWithText("Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.")
            .performScrollTo()
            .assertIsDisplayed()
    }

    @Test
    fun noConnectionOffersToTryAgain() {
        show(StoreStatusUiState(isLoading = false, trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).assertIsDisplayed()
        compose.onNodeWithTag(StoreStatusTags.RETRY).performScrollTo().performClick()

        assertEquals(listOf(StoreStatusEvent.CheckAgain), events)
    }

    @Test
    fun aServerOutageSaysTheInternetWorksAndOffersToTryAgain() {
        show(StoreStatusUiState(isLoading = false, trouble = ConnectionTrouble.Server))

        compose.onNodeWithText("Server sedang bermasalah").assertIsDisplayed()
        compose
            .onNodeWithText("Internet Anda tersambung. Masalahnya ada di pihak kami, coba lagi sebentar lagi. Anda tetap masuk.")
            .assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.offline_title)).assertDoesNotExist()
        compose.onNodeWithTag(StoreStatusTags.SIGN_OUT).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(StoreStatusTags.RETRY).performScrollTo().performClick()

        assertEquals(listOf(StoreStatusEvent.CheckAgain), events)
    }

    @Test
    fun aServerOutageWhileCheckingAgainKeepsTheCardAndBlamesTheServer() {
        show(store(StoreStatus.Pending, "Menunggu Persetujuan").copy(trouble = ConnectionTrouble.Server))

        compose.onNodeWithText("UD Kepiting Pak Budi").assertIsDisplayed()
        compose.onNodeWithText("Server sedang bermasalah. Coba lagi sebentar lagi.").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText(context.getString(R.string.offline_body)).assertDoesNotExist()
    }

    @Test
    fun noConnectionWhileCheckingAgainKeepsTheCardAndAsksToCheckTheInternet() {
        show(store(StoreStatus.Pending, "Menunggu Persetujuan").copy(trouble = ConnectionTrouble.NoInternet))

        compose.onNodeWithText(context.getString(R.string.offline_body)).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun checkingAgainDisablesTheButton() {
        show(store(StoreStatus.Pending, "Menunggu Persetujuan").copy(isChecking = true))

        compose.onNodeWithTag(StoreStatusTags.CHECK_AGAIN).performScrollTo().assertIsNotEnabled()
        compose.onNodeWithText(context.getString(R.string.store_status_checking)).assertExists()
    }
}
