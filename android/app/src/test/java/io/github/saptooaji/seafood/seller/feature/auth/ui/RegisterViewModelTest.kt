package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.feature.auth.domain.Registration
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
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
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.MethodSource
import org.junit.jupiter.params.provider.ValueSource

@OptIn(ExperimentalCoroutinesApi::class)
class RegisterViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val repository = FakeAuthRepository()
    private val draft = RegistrationDraft()

    private fun viewModel(handle: SavedStateHandle = SavedStateHandle()) = RegisterViewModel(repository, draft, handle)

    private fun RegisterViewModel.fill(form: Form = Form()) {
        onEvent(RegisterEvent.NameChanged(form.name))
        onEvent(RegisterEvent.EmailChanged(form.email))
        onEvent(RegisterEvent.PhoneChanged(form.phone))
        onEvent(RegisterEvent.PasswordChanged(form.password))
        onEvent(RegisterEvent.PasswordConfirmationChanged(form.confirmation))
    }

    // --- Valid ------------------------------------------------------------

    @Test
    fun `a complete form registers once with the phone number normalised`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(phone = "0812 3456 7890"))

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertEquals(
                listOf(Registration("Budi Santoso", "budi@example.com", "081234567890", "rahasia123", "rahasia123")),
                repository.registerCalls,
            )
            assertTrue(vm.uiState.value.registered)
            assertFalse(vm.uiState.value.isSubmitting)
        }

    @ParameterizedTest
    @ValueSource(strings = ["081234567890", "+6281234567890", "0812-3456-7890", " 0812 3456 7890 ", "0812345678", "081234567890123"])
    fun `Indonesian mobile numbers are accepted`(phone: String) =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(phone = phone))

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertNull(vm.uiState.value.phoneError)
            assertEquals(1, repository.registerCalls.size)
        }

    @Test
    fun `the name and email are trimmed, the passwords are sent as typed`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(
                Form(name = "  Budi Santoso ", email = " budi@example.com ", password = " rahasia 123 ", confirmation = " rahasia 123 "),
            )

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            val sent = repository.registerCalls.single()
            assertEquals("Budi Santoso", sent.name)
            assertEquals("budi@example.com", sent.email)
            assertEquals(" rahasia 123 ", sent.password)
            assertEquals(" rahasia 123 ", sent.passwordConfirmation)
        }

    // --- Refused before sending ------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("refusedFields", "refusedPasswords")
    fun `a wrong field is refused on the device`(
        case: String,
        form: Form,
        field: Field,
        message: UiText,
    ) = runTest(main.dispatcher) {
        val vm = viewModel()
        vm.fill(form)

        vm.onEvent(RegisterEvent.Submit)
        advanceUntilIdle()

        assertTrue(repository.registerCalls.isEmpty(), case)
        assertEquals(message, field.errorOf(vm.uiState.value), case)
    }

    @Test
    fun `every wrong field shows its error at once`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(name = "", email = "bukan-email", phone = "12345", password = "pendek", confirmation = "lain"))

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertTrue(state.nameError != null && state.emailError != null && state.phoneError != null && state.passwordError != null)
        }

    @Test
    fun `typing again clears the error of that field only`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(name = "", phone = "12345"))
            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            vm.onEvent(RegisterEvent.NameChanged("B"))

            assertNull(vm.uiState.value.nameError)
            assertTrue(vm.uiState.value.phoneError != null)
        }

    // --- Refused by the server -------------------------------------------

    @Test
    fun `an email already in use shows under the email field`() =
        runTest(main.dispatcher) {
            repository.registerResult = ApiResult.Invalid(mapOf("email" to listOf("The email has already been taken.")), null)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.register_error_email_taken), vm.uiState.value.emailError)
            assertFalse(vm.uiState.value.registered)
        }

    @Test
    fun `a phone number the server refuses shows under the phone field`() =
        runTest(main.dispatcher) {
            repository.registerResult = ApiResult.Invalid(mapOf("phone" to listOf("The phone field format is invalid.")), null)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.register_error_phone_invalid), vm.uiState.value.phoneError)
        }

    @Test
    fun `too many attempts disables the button for the seconds the server asks`() =
        runTest(main.dispatcher) {
            repository.registerResult = ApiResult.TooManyRequests(30)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            runCurrent()

            assertEquals(UiText.Resource(R.string.auth_error_too_many, listOf(30L)), vm.uiState.value.generalError)
            assertFalse(vm.uiState.value.canSubmit)

            advanceTimeBy(30_000)
            runCurrent()
            assertTrue(vm.uiState.value.canSubmit)
            assertNull(vm.uiState.value.generalError)
        }

    @Test
    fun `no connection shows the network message`() =
        runTest(main.dispatcher) {
            repository.registerResult = ApiResult.NetworkError
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    @Test
    fun `a server outage shows the server message, not the network one`() =
        runTest(main.dispatcher) {
            repository.registerResult = ApiResult.ServerUnreachable
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    // --- Edge cases -------------------------------------------------------

    @Test
    fun `pressing the button twice quickly sends one request`() =
        runTest(main.dispatcher) {
            repository.registerGate = CompletableDeferred()
            val vm = viewModel()
            vm.fill()

            vm.onEvent(RegisterEvent.Submit)
            runCurrent()
            assertTrue(vm.uiState.value.isSubmitting)
            vm.onEvent(RegisterEvent.Submit)
            runCurrent()

            repository.registerGate!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(1, repository.registerCalls.size)
        }

    @Test
    fun `going back to sign-in and returning keeps the name and email`() =
        runTest(main.dispatcher) {
            // Back pops the screen, so the next visit has a new view model and saved state.
            viewModel().fill()

            val again = viewModel(SavedStateHandle())

            assertEquals("Budi Santoso", again.uiState.value.name)
            assertEquals("budi@example.com", again.uiState.value.email)
            assertEquals("", again.uiState.value.password)
            assertEquals("", again.uiState.value.passwordConfirmation)
        }

    @Test
    fun `a recreated view model keeps the fields except the passwords`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            viewModel(handle).fill()

            val recreated = RegisterViewModel(repository, RegistrationDraft(), handle)

            assertEquals("Budi Santoso", recreated.uiState.value.name)
            assertEquals("budi@example.com", recreated.uiState.value.email)
            assertEquals("081234567890", recreated.uiState.value.phone)
            assertEquals("", recreated.uiState.value.password)
        }

    @Test
    fun `a finished registration forgets the draft`() =
        runTest(main.dispatcher) {
            viewModel().apply {
                fill()
                onEvent(RegisterEvent.Submit)
            }
            advanceUntilIdle()

            assertEquals("", viewModel().uiState.value.name)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `neither password goes into the saved state`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            val vm = viewModel(handle)
            vm.fill(Form(password = "rahasia123", confirmation = "rahasia124"))
            vm.onEvent(RegisterEvent.Submit)
            advanceUntilIdle()

            val stored = handle.keys().map { key -> handle.get<Any?>(key)?.toString().orEmpty() }
            assertFalse(handle.keys().any { it.contains("password", ignoreCase = true) })
            assertFalse(stored.any { it.contains("rahasia12") })
        }

    @Test
    fun `a registration never prints its passwords`() {
        val registration = Registration("Budi Santoso", "budi@example.com", "081234567890", "rahasia123", "rahasia123")

        assertFalse(registration.toString().contains("rahasia123"))
    }

    data class Form(
        val name: String = "Budi Santoso",
        val email: String = "budi@example.com",
        val phone: String = "081234567890",
        val password: String = "rahasia123",
        val confirmation: String = password,
    )

    enum class Field(
        val errorOf: (RegisterUiState) -> UiText?,
    ) {
        Name({ it.nameError }),
        Email({ it.emailError }),
        Phone({ it.phoneError }),
        Password({ it.passwordError }),
        Confirmation({ it.passwordConfirmationError }),
    }

    companion object {
        private fun refused(
            case: String,
            form: Form,
            field: Field,
            message: Int,
        ) = Arguments.of(case, form, field, UiText.Resource(message))

        @JvmStatic
        fun refusedFields(): List<Arguments> =
            listOf(
                refused("empty name", Form(name = ""), Field.Name, R.string.register_error_name_required),
                refused("blank name", Form(name = "   "), Field.Name, R.string.register_error_name_required),
                refused("name over 255", Form(name = "a".repeat(256)), Field.Name, R.string.register_error_name_too_long),
                refused("empty email", Form(email = ""), Field.Email, R.string.auth_error_email_required),
                refused("malformed email", Form(email = "bukan-email"), Field.Email, R.string.auth_error_email_invalid),
                refused("email over 255", Form(email = "a".repeat(250) + "@x.com"), Field.Email, R.string.auth_error_email_too_long),
                refused("phone 12345", Form(phone = "12345"), Field.Phone, R.string.register_error_phone_invalid),
                refused("phone without 08", Form(phone = "6281234567890"), Field.Phone, R.string.register_error_phone_invalid),
                refused("phone too short", Form(phone = "081234567"), Field.Phone, R.string.register_error_phone_invalid),
                refused("phone too long", Form(phone = "0812345678901234"), Field.Phone, R.string.register_error_phone_invalid),
                refused("phone with letters", Form(phone = "0812abc45678"), Field.Phone, R.string.register_error_phone_invalid),
                refused("empty phone", Form(phone = ""), Field.Phone, R.string.register_error_phone_invalid),
            )

        @JvmStatic
        fun refusedPasswords(): List<Arguments> =
            listOf(
                refused("password of 7 characters", Form(password = "rahasia"), Field.Password, R.string.register_error_password_too_short),
                refused(
                    "different confirmation",
                    Form(password = "rahasia123", confirmation = "rahasia124"),
                    Field.Confirmation,
                    R.string.register_error_password_mismatch,
                ),
                refused(
                    "empty confirmation",
                    Form(password = "rahasia123", confirmation = ""),
                    Field.Confirmation,
                    R.string.register_error_password_mismatch,
                ),
            )
    }
}
