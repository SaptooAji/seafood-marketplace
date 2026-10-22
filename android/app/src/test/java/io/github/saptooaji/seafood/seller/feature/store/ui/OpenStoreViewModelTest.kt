package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.FakeDeviceLocation
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
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
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.MethodSource

@OptIn(ExperimentalCoroutinesApi::class)
class OpenStoreViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val repository = FakeStoreRepository()
    private val location = FakeDeviceLocation()

    private fun viewModel(handle: SavedStateHandle = SavedStateHandle()) = OpenStoreViewModel(repository, location, handle)

    private fun OpenStoreViewModel.fill(
        form: Form = Form(),
        pin: GeoPoint? = MUARA_ANGKE,
    ) {
        type(StoreField.Name, form.name)
        type(StoreField.Description, form.description)
        type(StoreField.Phone, form.phone)
        type(StoreField.Address, form.address)
        type(StoreField.City, form.city)
        type(StoreField.BankName, form.bankName)
        type(StoreField.BankAccountNo, form.bankAccountNo)
        type(StoreField.BankAccountName, form.bankAccountName)
        if (pin != null) onEvent(OpenStoreEvent.PinMoved(pin))
    }

    private fun OpenStoreViewModel.type(
        field: StoreField,
        value: String,
    ) = onEvent(OpenStoreEvent.FieldChanged(field, value))

    // --- Valid ------------------------------------------------------------

    @Test
    fun `a complete form with a pin opens the store with coordinates of seven decimals`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(pin = GeoPoint(-6.1067, 106.7740))

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            val sent = repository.openCalls.single()
            assertEquals("-6.1067000", sent.lat.toPlainString())
            assertEquals("106.7740000", sent.lng.toPlainString())
            assertEquals("UD Kepiting Pak Budi", sent.name)
            assertEquals("BCA", sent.bankName)
            assertEquals("1234567890", sent.bankAccountNo)
            assertEquals("Budi Santoso", sent.bankAccountName)
            assertTrue(vm.uiState.value.opened)
            assertFalse(vm.uiState.value.isSubmitting)
        }

    @Test
    fun `an empty description is sent as null`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(description = "   "))

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertNull(repository.openCalls.single().description)
        }

    @Test
    fun `text is trimmed and the phone number loses its spaces`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(name = "  UD Kepiting Pak Budi ", phone = "0812 3456 7890", city = " Jakarta Utara ", description = " Segar "))

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            val sent = repository.openCalls.single()
            assertEquals("UD Kepiting Pak Budi", sent.name)
            assertEquals("081234567890", sent.phone)
            assertEquals("Jakarta Utara", sent.city)
            assertEquals("Segar", sent.description)
        }

    @Test
    fun `the longest values the server takes are accepted`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(
                Form(
                    name = "a".repeat(100),
                    description = "a".repeat(2000),
                    address = "a".repeat(500),
                    city = "a".repeat(100),
                    bankName = "a".repeat(100),
                    bankAccountNo = "1".repeat(20),
                    bankAccountName = "a".repeat(255),
                ),
            )

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(emptyMap<StoreField, UiText>(), vm.uiState.value.form.errors)
            assertEquals(1, repository.openCalls.size)
        }

    // --- Refused before sending ------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("refusedForms")
    fun `a wrong field is refused on the device`(
        case: String,
        form: Form,
        field: StoreField,
        message: UiText,
    ) = runTest(main.dispatcher) {
        val vm = viewModel()
        vm.fill(form)

        vm.onEvent(OpenStoreEvent.Submit)
        advanceUntilIdle()

        assertTrue(repository.openCalls.isEmpty(), case)
        assertEquals(message, vm.uiState.value.form.errors[field], case)
    }

    @Test
    fun `a store without a pin is refused`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(pin = null)

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertTrue(repository.openCalls.isEmpty())
            assertEquals(UiText.Resource(R.string.store_error_pin_required), vm.uiState.value.form.errors[StoreField.Pin])
        }

    @Test
    fun `typing again clears the error of that field only, and moving the map clears the pin error`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(Form(name = "", bankAccountNo = "12ab"), pin = null)
            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            vm.type(StoreField.Name, "U")
            vm.onEvent(OpenStoreEvent.PinMoved(MUARA_ANGKE))

            val errors = vm.uiState.value.form.errors
            assertNull(errors[StoreField.Name])
            assertNull(errors[StoreField.Pin])
            assertTrue(errors[StoreField.BankAccountNo] != null)
        }

    // --- Refused by the server -------------------------------------------

    @Test
    fun `an account number the server refuses shows under the account field`() =
        runTest(main.dispatcher) {
            repository.openResult =
                ApiResult.Invalid(mapOf("bank_account_no" to listOf("The bank account no field format is invalid.")), null)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(
                UiText.Resource(R.string.store_error_bank_account_no_invalid),
                vm.uiState.value.form.errors[StoreField.BankAccountNo],
            )
            assertFalse(vm.uiState.value.opened)
        }

    @Test
    fun `refused coordinates show at the pin`() =
        runTest(main.dispatcher) {
            repository.openResult = ApiResult.Invalid(mapOf("lat" to listOf("The lat field must be between -90 and 90.")), null)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.store_error_pin_required), vm.uiState.value.form.errors[StoreField.Pin])
        }

    @Test
    fun `a refused field the form does not have shows the server message`() =
        runTest(main.dispatcher) {
            repository.openResult = ApiResult.Invalid(mapOf("stock_reminder_time" to listOf("Wrong.")), null)
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
        }

    @Test
    fun `a seller who already has a store is told so and offered the status`() =
        runTest(main.dispatcher) {
            repository.openResult = ApiResult.Conflict("Anda sudah memiliki toko.")
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertTrue(vm.uiState.value.alreadyHasStore)
            assertFalse(vm.uiState.value.opened)
            assertEquals("UD Kepiting Pak Budi", vm.uiState.value.form.name)
        }

    @Test
    fun `no connection shows the network message and allows another try`() =
        runTest(main.dispatcher) {
            repository.openResult = ApiResult.NetworkError
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.generalError)
            assertTrue(vm.uiState.value.canSubmit)
        }

    @Test
    fun `a server outage shows the server message and keeps the form`() =
        runTest(main.dispatcher) {
            repository.openResult = ApiResult.ServerUnreachable
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
            assertEquals("UD Kepiting Pak Budi", vm.uiState.value.form.name)
            assertTrue(vm.uiState.value.canSubmit)
        }

    // --- Location ---------------------------------------------------------

    @Test
    fun `the map starts at the centre of Jakarta Utara with no pin yet`() =
        runTest(main.dispatcher) {
            val state = viewModel().uiState.value

            assertEquals(GeoPoint.JAKARTA_UTARA, state.camera.target)
            assertNull(state.form.pin)
        }

    @Test
    fun `a denied location permission centres the map on Jakarta Utara and the form can still be sent`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(pin = null)
            val moves = vm.uiState.value.camera.move

            vm.onEvent(OpenStoreEvent.LocationDenied)
            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(GeoPoint.JAKARTA_UTARA, state.camera.target)
            assertTrue(state.camera.move > moves)
            assertEquals(UiText.Resource(R.string.store_location_denied), state.locationMessage)
            assertEquals(0, location.calls)
            assertEquals(1, repository.openCalls.size)
            assertTrue(state.opened)
        }

    @Test
    fun `a granted permission moves the map and the pin to the device`() =
        runTest(main.dispatcher) {
            location.point = MUARA_ANGKE
            val vm = viewModel()
            val moves = vm.uiState.value.camera.move

            vm.onEvent(OpenStoreEvent.LocationGranted)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(MUARA_ANGKE, state.camera.target)
            assertTrue(state.camera.move > moves)
            assertEquals(MUARA_ANGKE, state.form.pin)
            assertNull(state.locationMessage)
        }

    @Test
    fun `a device without a location fix keeps the map where it is`() =
        runTest(main.dispatcher) {
            location.point = null
            val vm = viewModel()
            vm.onEvent(OpenStoreEvent.PinMoved(MUARA_ANGKE))

            vm.onEvent(OpenStoreEvent.LocationGranted)
            advanceUntilIdle()

            assertEquals(MUARA_ANGKE, vm.uiState.value.form.pin)
            assertEquals(UiText.Resource(R.string.store_location_unavailable), vm.uiState.value.locationMessage)
        }

    @Test
    fun `moving the map moves the pin without moving the camera again`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            val moves = vm.uiState.value.camera.move

            vm.onEvent(OpenStoreEvent.PinMoved(MUARA_ANGKE))

            assertEquals(MUARA_ANGKE, vm.uiState.value.form.pin)
            assertEquals(moves, vm.uiState.value.camera.move)
        }

    // --- Edge cases -------------------------------------------------------

    @Test
    fun `a rotation keeps the pin and the profile`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            viewModel(handle).fill(pin = MUARA_ANGKE)

            val recreated = viewModel(handle)

            val state = recreated.uiState.value
            assertEquals(MUARA_ANGKE, state.form.pin)
            assertEquals(MUARA_ANGKE, state.camera.target)
            assertEquals("UD Kepiting Pak Budi", state.form.name)
            assertEquals("081234567890", state.form.phone)
            assertEquals("Jakarta Utara", state.form.city)
        }

    @Test
    fun `coordinates are rounded to seven decimals, not cut`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            vm.fill(pin = GeoPoint(-6.10670005, 106.77399996))

            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            val sent = repository.openCalls.single()
            assertEquals("-6.1067001", sent.lat.toPlainString())
            assertEquals("106.7740000", sent.lng.toPlainString())
        }

    @Test
    fun `pressing the button twice quickly sends one request`() =
        runTest(main.dispatcher) {
            repository.openGate = CompletableDeferred()
            val vm = viewModel()
            vm.fill()

            vm.onEvent(OpenStoreEvent.Submit)
            runCurrent()
            assertTrue(vm.uiState.value.isSubmitting)
            vm.onEvent(OpenStoreEvent.Submit)
            runCurrent()

            repository.openGate!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(1, repository.openCalls.size)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the bank account stays out of the saved state`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            val vm = viewModel(handle)
            vm.fill(Form(bankName = "Bank Nusantara", bankAccountNo = "9876543210", bankAccountName = "Siti Rahayu"))
            vm.onEvent(OpenStoreEvent.Submit)
            advanceUntilIdle()

            val stored = handle.keys().map { key -> handle.get<Any?>(key)?.toString().orEmpty() }
            assertFalse(handle.keys().any { it.contains("bank", ignoreCase = true) })
            assertFalse(stored.any { it.contains("9876543210") || it.contains("Siti Rahayu") || it.contains("Bank Nusantara") })

            // After process death the account fields start empty.
            val restored = viewModel(handle).uiState.value.form
            assertEquals("", restored.bankAccountNo)
            assertEquals("", restored.bankAccountName)
        }

    data class Form(
        val name: String = "UD Kepiting Pak Budi",
        val description: String = "Pengepul kepiting bakau dari tambak Muara Angke sejak 2009.",
        val phone: String = "081234567890",
        val address: String = "Jl. Dermaga Ujung No. 12, Muara Angke",
        val city: String = "Jakarta Utara",
        val bankName: String = "BCA",
        val bankAccountNo: String = "1234567890",
        val bankAccountName: String = "Budi Santoso",
    )

    companion object {
        private val MUARA_ANGKE = GeoPoint(-6.1067, 106.7740)

        private fun refused(
            case: String,
            form: Form,
            field: StoreField,
            message: Int,
        ) = Arguments.of(case, form, field, UiText.Resource(message))

        @JvmStatic
        fun refusedForms(): List<Arguments> =
            listOf(
                refused("empty name", Form(name = " "), StoreField.Name, R.string.store_error_name_required),
                refused("name of 101 characters", Form(name = "a".repeat(101)), StoreField.Name, R.string.store_error_name_too_long),
                refused(
                    "description over 2000",
                    Form(description = "a".repeat(2001)),
                    StoreField.Description,
                    R.string.store_error_description_too_long,
                ),
                refused("landline 021555", Form(phone = "021555"), StoreField.Phone, R.string.register_error_phone_invalid),
                refused("empty address", Form(address = ""), StoreField.Address, R.string.store_error_address_required),
                refused("address over 500", Form(address = "a".repeat(501)), StoreField.Address, R.string.store_error_address_too_long),
                refused("empty city", Form(city = ""), StoreField.City, R.string.store_error_city_required),
                refused("city over 100", Form(city = "a".repeat(101)), StoreField.City, R.string.store_error_city_too_long),
                refused("empty bank", Form(bankName = ""), StoreField.BankName, R.string.store_error_bank_name_required),
                refused("bank over 100", Form(bankName = "a".repeat(101)), StoreField.BankName, R.string.store_error_bank_name_too_long),
                refused(
                    "account 12ab",
                    Form(bankAccountNo = "12ab"),
                    StoreField.BankAccountNo,
                    R.string.store_error_bank_account_no_invalid,
                ),
                refused(
                    "account of 4 digits",
                    Form(bankAccountNo = "1234"),
                    StoreField.BankAccountNo,
                    R.string.store_error_bank_account_no_invalid,
                ),
                refused(
                    "account of 21 digits",
                    Form(bankAccountNo = "1".repeat(21)),
                    StoreField.BankAccountNo,
                    R.string.store_error_bank_account_no_invalid,
                ),
                refused(
                    "account with a space",
                    Form(bankAccountNo = "1234 5678"),
                    StoreField.BankAccountNo,
                    R.string.store_error_bank_account_no_invalid,
                ),
                refused(
                    "empty account name",
                    Form(bankAccountName = ""),
                    StoreField.BankAccountName,
                    R.string.store_error_bank_account_name_required,
                ),
                refused(
                    "account name over 255",
                    Form(bankAccountName = "a".repeat(256)),
                    StoreField.BankAccountName,
                    R.string.store_error_bank_account_name_too_long,
                ),
            )
    }
}
