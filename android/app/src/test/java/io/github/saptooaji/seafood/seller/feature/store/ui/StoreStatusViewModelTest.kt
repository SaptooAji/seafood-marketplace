package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension

@OptIn(ExperimentalCoroutinesApi::class)
class StoreStatusViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val stores = FakeStoreRepository()
    private val auth = FakeAuthRepository()

    private fun viewModel(serverMessage: String? = null) =
        StoreStatusViewModel(stores, auth, SavedStateHandle(mapOf("serverMessage" to serverMessage)))

    // --- Showing the status ----------------------------------------------

    @Test
    fun `a pending store shows its name and the server's label`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            val store = vm.uiState.value.store!!
            assertEquals("UD Kepiting Pak Budi", store.name)
            assertEquals(StoreStatus.Pending, store.status)
            assertEquals("Menunggu Persetujuan", store.statusLabel)
            assertFalse(vm.uiState.value.isLoading)
        }

    @Test
    fun `a rejected store shows the admin's reason`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.REJECTED_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(
                "Foto KTP buram.",
                vm.uiState.value.store!!
                    .rejectionReason,
            )
        }

    @Test
    fun `a suspension found by another screen shows the server's message`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.SUSPENDED_STORE))

            val vm = viewModel(serverMessage = "Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.")
            advanceUntilIdle()

            assertEquals(
                StoreStatus.Suspended,
                vm.uiState.value.store!!
                    .status,
            )
            assertEquals("Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.", vm.uiState.value.serverMessage)
        }

    // --- Checking again ---------------------------------------------------

    @Test
    fun `checking again when the admin has verified the store opens the main screens`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE), found(FakeStoreRepository.ACTIVE_STORE))
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(StoreStatusEvent.CheckAgain)
            advanceUntilIdle()

            assertEquals(StoreStatusDestination.Main, vm.uiState.value.destination)
            assertEquals(2, stores.currentCalls)
        }

    @Test
    fun `checking again shows a rejected store that is pending again`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.REJECTED_STORE), found(FakeStoreRepository.PENDING_STORE))
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(StoreStatusEvent.CheckAgain)
            advanceUntilIdle()

            assertEquals(
                StoreStatus.Pending,
                vm.uiState.value.store!!
                    .status,
            )
            assertNull(
                vm.uiState.value.store!!
                    .rejectionReason,
            )
            assertNull(vm.uiState.value.destination)
        }

    @Test
    fun `a store that is gone sends the seller to open one`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(FakeStoreRepository.NO_STORE)

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StoreStatusDestination.OpenStore, vm.uiState.value.destination)
        }

    @Test
    fun `the button is busy while checking, and a second press sends nothing`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE))
            val vm = viewModel()
            advanceUntilIdle()
            stores.currentGate = CompletableDeferred()

            vm.onEvent(StoreStatusEvent.CheckAgain)
            runCurrent()
            assertTrue(vm.uiState.value.isChecking)
            vm.onEvent(StoreStatusEvent.CheckAgain)
            runCurrent()

            stores.currentGate!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(2, stores.currentCalls)
            assertFalse(vm.uiState.value.isChecking)
        }

    // --- Errors -----------------------------------------------------------

    @Test
    fun `no connection shows the offline state, and trying again shows the store`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.NetworkError, found(FakeStoreRepository.PENDING_STORE))
            val vm = viewModel()
            advanceUntilIdle()
            assertTrue(vm.uiState.value.offline)

            vm.onEvent(StoreStatusEvent.CheckAgain)
            advanceUntilIdle()

            assertFalse(vm.uiState.value.offline)
            assertEquals(
                StoreStatus.Pending,
                vm.uiState.value.store!!
                    .status,
            )
        }

    @Test
    fun `no connection while checking again keeps the store on screen`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE), ApiResult.NetworkError)
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(StoreStatusEvent.CheckAgain)
            advanceUntilIdle()

            assertEquals(
                "UD Kepiting Pak Budi",
                vm.uiState.value.store!!
                    .name,
            )
            assertTrue(vm.uiState.value.offline)
        }

    // --- Signing out (MOB-6) ----------------------------------------------

    @Test
    fun `signing out deletes the token, forgets the store, and opens sign-in`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE))
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(StoreStatusEvent.SignOut)
            advanceUntilIdle()

            assertEquals(1, auth.logoutCalls)
            assertTrue(auth.tokenCleared)
            assertNull(vm.uiState.value.store)
            assertEquals(StoreStatusDestination.Login, vm.uiState.value.destination)
        }

    @Test
    fun `signing out works from the offline state too`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.NetworkError)
            val vm = viewModel()
            advanceUntilIdle()

            vm.onEvent(StoreStatusEvent.SignOut)
            advanceUntilIdle()

            assertEquals(StoreStatusDestination.Login, vm.uiState.value.destination)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the status screen keeps no bank account in memory`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            assertFalse(
                vm.uiState.value
                    .toString()
                    .contains("7700112233"),
            )
            assertFalse(
                vm.uiState.value
                    .toString()
                    .contains("BCA"),
            )
        }
}
