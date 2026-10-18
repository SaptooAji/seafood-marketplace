package io.github.saptooaji.seafood.seller.feature.start

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension

/** Where the app opens: sign-in, or straight to the store when a token still works. */
@OptIn(ExperimentalCoroutinesApi::class)
class StartViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val repository = FakeAuthRepository()
    private val tokenStore = FakeTokenStore()

    @Test
    fun `without a token the app opens on sign-in, without calling the API`() =
        runTest(main.dispatcher) {
            val vm = StartViewModel(tokenStore, repository)
            advanceUntilIdle()

            assertEquals(StartDestination.SignIn, vm.destination.value)
            assertEquals(0, repository.meCalls)
        }

    @Test
    fun `a stored token that still works skips sign-in`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"

            val vm = StartViewModel(tokenStore, repository)
            advanceUntilIdle()

            assertEquals(StartDestination.Main, vm.destination.value)
            assertEquals(1, repository.meCalls)
        }

    @Test
    fun `a stored token the API refuses opens sign-in`() =
        runTest(main.dispatcher) {
            // AuthInterceptor has already deleted the token by the time me() returns.
            tokenStore.state.value = "sfm_expired"
            repository.meResult = ApiResult.Unauthorized

            val vm = StartViewModel(tokenStore, repository)
            advanceUntilIdle()

            assertEquals(StartDestination.SignIn, vm.destination.value)
        }

    @Test
    fun `no connection keeps a signed-in seller signed in`() =
        runTest(main.dispatcher) {
            tokenStore.state.value = "sfm_abc"
            repository.meResult = ApiResult.NetworkError

            val vm = StartViewModel(tokenStore, repository)
            advanceUntilIdle()

            assertEquals(StartDestination.Main, vm.destination.value)
        }

    @Test
    fun `nothing is decided before the check finishes`() =
        runTest(main.dispatcher) {
            val vm = StartViewModel(tokenStore, repository)

            assertEquals(StartDestination.Checking, vm.destination.value)
        }
}
