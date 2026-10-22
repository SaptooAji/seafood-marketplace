package io.github.saptooaji.seafood.seller.feature.auth.ui

import android.content.Context
import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.FakeGoogleIdTokenProvider
import io.github.saptooaji.seafood.seller.feature.auth.google.GoogleIdTokenResult
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import io.mockk.mockk
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceTimeBy
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.ValueSource

@OptIn(ExperimentalCoroutinesApi::class)
class LoginViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val repository = FakeAuthRepository()
    private val google = FakeGoogleIdTokenProvider()

    /** Credential Manager needs an activity; the fake provider never touches it. */
    private val activity = mockk<Context>()

    private fun viewModel(
        sessionExpired: Boolean = false,
        google: FakeGoogleIdTokenProvider = this.google,
    ) = LoginViewModel(repository, google, SavedStateHandle(mapOf("sessionExpired" to sessionExpired)))

    private fun LoginViewModel.fill(
        email: String,
        password: String,
    ) {
        onEvent(LoginEvent.EmailChanged(email))
        onEvent(LoginEvent.PasswordChanged(password))
    }

    // --- Valid ------------------------------------------------------------

    @Test
    fun `correct credentials sign in once`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertEquals(listOf("budi@example.com" to "rahasia123"), repository.loginCalls)
            assertTrue(vm.uiState.value.loggedIn)
            assertFalse(vm.uiState.value.isSubmitting)
        }

    @Test
    fun `the email is trimmed and the password is sent as typed`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill("  budi@example.com ", " rahasia 123 ")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertEquals(listOf("budi@example.com" to " rahasia 123 "), repository.loginCalls)
        }

    // --- Refused before sending ------------------------------------------

    @ParameterizedTest
    @ValueSource(strings = ["", "   ", "bukan-email", "budi@", "@example.com"])
    fun `an empty or malformed email is refused on the device`(email: String) =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(email, "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertTrue(repository.loginCalls.isEmpty())
            assertTrue(vm.uiState.value.emailError is UiText.Resource)
        }

    @Test
    fun `an email longer than 255 characters is refused on the device`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill("a".repeat(250) + "@x.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertTrue(repository.loginCalls.isEmpty())
            assertEquals(UiText.Resource(R.string.auth_error_email_too_long), vm.uiState.value.emailError)
        }

    @Test
    fun `an empty password is refused on the device`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill("budi@example.com", "")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertTrue(repository.loginCalls.isEmpty())
            assertEquals(UiText.Resource(R.string.login_error_password_required), vm.uiState.value.passwordError)
        }

    @Test
    fun `typing again clears the error of that field`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill("", "")
            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            vm.onEvent(LoginEvent.EmailChanged("b"))

            assertNull(vm.uiState.value.emailError)
            assertTrue(vm.uiState.value.passwordError != null)
        }

    // --- Refused by the server -------------------------------------------

    @Test
    fun `wrong credentials show one message under the email field`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.Invalid(mapOf("email" to listOf("These credentials do not match our records.")), null)
            val vm = viewModel()
            vm.fill("budi@example.com", "salah")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            // AUTH-3: the same message whatever the reason; the app's own Indonesian text.
            assertEquals(UiText.Resource(R.string.login_error_credentials), vm.uiState.value.emailError)
            assertFalse(vm.uiState.value.loggedIn)
        }

    @Test
    fun `too many attempts disables the button for the seconds the server asks`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.TooManyRequests(45)
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            runCurrent()

            assertEquals(UiText.Resource(R.string.auth_error_too_many, listOf(45L)), vm.uiState.value.generalError)
            assertFalse(vm.uiState.value.canSubmit)

            advanceTimeBy(30_000)
            runCurrent()
            assertEquals(UiText.Resource(R.string.auth_error_too_many, listOf(15L)), vm.uiState.value.generalError)

            advanceTimeBy(15_000)
            runCurrent()
            assertTrue(vm.uiState.value.canSubmit)
            assertNull(vm.uiState.value.generalError)

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()
            assertEquals(2, repository.loginCalls.size)
        }

    @Test
    fun `submitting while waiting out a 429 sends nothing`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.TooManyRequests(45)
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")
            vm.onEvent(LoginEvent.Submit)
            runCurrent()

            vm.onEvent(LoginEvent.Submit)
            runCurrent()

            assertEquals(1, repository.loginCalls.size)
        }

    @Test
    fun `no connection shows the network message`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.NetworkError
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    @Test
    fun `a server outage shows the server message, not the network one`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.ServerUnreachable
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    @Test
    fun `a server error shows a general message`() =
        runTest(main.dispatcher) {
            repository.loginResult = ApiResult.ServerError
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
        }

    // --- Edge cases -------------------------------------------------------

    @Test
    fun `pressing Masuk twice quickly sends one request`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            repository.loginGate = gate
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.Submit)
            runCurrent()
            assertTrue(vm.uiState.value.isSubmitting)
            assertFalse(vm.uiState.value.canSubmit)
            vm.onEvent(LoginEvent.Submit)
            runCurrent()

            gate.complete(Unit)
            advanceUntilIdle()
            assertEquals(1, repository.loginCalls.size)
        }

    @Test
    fun `the email survives a recreated view model, the password does not`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle(mapOf("sessionExpired" to false))
            val first = LoginViewModel(repository, google, handle)
            first.fill("budi@example.com", "rahasia123")

            val second = LoginViewModel(repository, google, handle)

            assertEquals("budi@example.com", second.uiState.value.email)
            assertEquals("", second.uiState.value.password)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the password never goes into the saved state`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle(mapOf("sessionExpired" to false))
            val vm = LoginViewModel(repository, google, handle)
            vm.fill("budi@example.com", "rahasia123")
            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            val stored = handle.keys().map { key -> handle.get<Any?>(key)?.toString().orEmpty() }
            assertFalse(handle.keys().any { it.contains("password", ignoreCase = true) })
            assertFalse(stored.any { it.contains("rahasia123") })
        }

    @Test
    fun `arriving after the session ended shows the session message`() =
        runTest(main.dispatcher) {
            val vm = viewModel(sessionExpired = true)

            assertTrue(vm.uiState.value.sessionExpired)
        }

    // --- Google -----------------------------------------------------------

    @Test
    fun `a Google account signs in with its ID token`() =
        runTest(main.dispatcher) {
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            assertEquals(listOf(FakeGoogleIdTokenProvider.ID_TOKEN), repository.googleCalls)
            assertTrue(vm.uiState.value.loggedIn)
            assertFalse(vm.uiState.value.isGoogleSubmitting)
        }

    @Test
    fun `closing the account picker goes back quietly`() =
        runTest(main.dispatcher) {
            google.result = GoogleIdTokenResult.Cancelled
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            assertTrue(repository.googleCalls.isEmpty())
            assertNull(vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    @Test
    fun `no Google account on the device asks to add one`() =
        runTest(main.dispatcher) {
            google.result = GoogleIdTokenResult.NoAccount
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.login_google_no_account), vm.uiState.value.generalError)
            assertTrue(repository.googleCalls.isEmpty())
        }

    @Test
    fun `another Credential Manager error shows a general message`() =
        runTest(main.dispatcher) {
            google.result = GoogleIdTokenResult.Failed
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.login_google_failed), vm.uiState.value.generalError)
        }

    @Test
    fun `an ID token the server refuses shows the Google failure`() =
        runTest(main.dispatcher) {
            repository.googleResult = ApiResult.Invalid(mapOf("id_token" to listOf("The Google sign-in could not be verified.")), null)
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.login_google_failed), vm.uiState.value.generalError)
            assertFalse(vm.uiState.value.loggedIn)
        }

    @Test
    fun `too many Google attempts waits out the server`() =
        runTest(main.dispatcher) {
            repository.googleResult = ApiResult.TooManyRequests(20)
            val vm = viewModel()

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            runCurrent()

            assertEquals(UiText.Resource(R.string.auth_error_too_many, listOf(20L)), vm.uiState.value.generalError)
            assertFalse(vm.uiState.value.canSubmit)
        }

    @Test
    fun `while the picker is open neither button sends again`() =
        runTest(main.dispatcher) {
            google.gate = CompletableDeferred()
            val vm = viewModel()
            vm.fill("budi@example.com", "rahasia123")

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            runCurrent()
            assertTrue(vm.uiState.value.isGoogleSubmitting)
            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            vm.onEvent(LoginEvent.Submit)
            runCurrent()

            google.gate!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(1, google.calls)
            assertTrue(repository.loginCalls.isEmpty())
        }

    @Test
    fun `without a server client ID there is no Google button, and email still works`() =
        runTest(main.dispatcher) {
            val vm = viewModel(google = FakeGoogleIdTokenProvider(isAvailable = false))
            assertFalse(vm.uiState.value.googleAvailable)

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            vm.fill("budi@example.com", "rahasia123")
            vm.onEvent(LoginEvent.Submit)
            advanceUntilIdle()

            assertTrue(repository.googleCalls.isEmpty())
            assertEquals(1, repository.loginCalls.size)
        }

    @Test
    fun `the ID token never goes into the saved state`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle(mapOf("sessionExpired" to false))
            val vm = LoginViewModel(repository, google, handle)

            vm.onEvent(LoginEvent.GoogleSignIn(activity))
            advanceUntilIdle()

            val stored = handle.keys().map { key -> handle.get<Any?>(key)?.toString().orEmpty() }
            assertFalse(stored.any { it.contains(FakeGoogleIdTokenProvider.ID_TOKEN) })
        }
}
