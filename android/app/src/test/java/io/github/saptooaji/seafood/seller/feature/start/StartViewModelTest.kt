package io.github.saptooaji.seafood.seller.feature.start

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension

/** Where the app opens: sign-in, opening a store, the store status, or the main screens. */
@OptIn(ExperimentalCoroutinesApi::class)
class StartViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val repository = FakeStoreRepository()
    private val tokenStore = FakeTokenStore()

    private fun viewModel() = StartViewModel(tokenStore, repository)

    @Test
    fun `without a token the app opens on sign-in, without calling the API`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StartDestination.Login, vm.destination.value)
            assertEquals(0, repository.currentCalls)
        }

    @Test
    fun `an active store opens the main screens`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"
            repository.answerCurrent(found(FakeStoreRepository.ACTIVE_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StartDestination.Main, vm.destination.value)
            assertEquals(1, repository.currentCalls)
        }

    @Test
    fun `a seller without a store opens a store`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"
            repository.answerCurrent(FakeStoreRepository.NO_STORE)

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StartDestination.OpenStore, vm.destination.value)
        }

    @Test
    fun `a pending store opens its status`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"
            repository.answerCurrent(found(FakeStoreRepository.PENDING_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StartDestination.StoreStatus, vm.destination.value)
        }

    @Test
    fun `a stored token the API refuses opens sign-in`() =
        runTest(main.dispatcher) {
            // AuthInterceptor has already deleted the token by the time current() returns.
            tokenStore.state.value = "sfm_expired"
            repository.answerCurrent(ApiResult.Unauthorized)

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(StartDestination.Login, vm.destination.value)
        }

    @Test
    fun `no connection shows the offline screen, not sign-in, and trying again moves on`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"
            repository.answerCurrent(ApiResult.NetworkError, found(FakeStoreRepository.ACTIVE_STORE))

            val vm = viewModel()
            advanceUntilIdle()
            assertEquals(StartDestination.Offline, vm.destination.value)
            assertEquals("sfm_abc", tokenStore.state.value)

            vm.retry()
            advanceUntilIdle()

            assertEquals(StartDestination.Main, vm.destination.value)
            assertEquals(2, repository.currentCalls)
        }

    @Test
    fun `nothing is decided before the check finishes`() =
        runTest(main.dispatcher) {
            val vm = viewModel()

            assertEquals(StartDestination.Checking, vm.destination.value)
        }
}
