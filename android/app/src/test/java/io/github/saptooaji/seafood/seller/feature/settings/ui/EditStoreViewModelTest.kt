package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.FakeDeviceLocation
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreField
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.TestScope
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension
import java.math.BigDecimal
import java.time.LocalTime

/**
 * Editing the store profile, location, and bank account (#245): the store
 * form of #234, filled from `GET seller/store` and saved with `PUT seller/store`.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class EditStoreViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val stores = FakeStoreRepository().apply { answerCurrent(found(FakeStoreRepository.OWNED_STORE)) }
    private val location = FakeDeviceLocation()

    private fun viewModel(handle: SavedStateHandle = SavedStateHandle()) = EditStoreViewModel(stores, location, handle)

    private fun TestScope.loaded(handle: SavedStateHandle = SavedStateHandle()): EditStoreViewModel {
        val vm = viewModel(handle)
        advanceUntilIdle()
        return vm
    }

    // --- Loading ----------------------------------------------------------

    @Test
    fun `the form starts from the store on the server, with the map at its pin`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            val state = vm.uiState.value
            assertFalse(state.isLoading)
            val form = state.form
            assertEquals("UD Kepiting Pak Budi", form.name)
            assertEquals("", form.description)
            assertEquals("081234567890", form.phone)
            assertEquals("Jl. Dermaga Ujung No. 12, Muara Angke", form.address)
            assertEquals("Jakarta Utara", form.city)
            assertEquals("BCA", form.bankName)
            assertEquals("7700112233", form.bankAccountNo)
            assertEquals("Budi Santoso", form.bankAccountName)
            assertEquals(GeoPoint(-6.1067, 106.774), form.pin)
            assertEquals(GeoPoint(-6.1067, 106.774), state.camera.target)
            assertTrue(form.errors.isEmpty())
        }

    @Test
    fun `a store description is filled in`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE.copy(description = "Pengepul kepiting bakau sejak 2009.")))

            val vm = loaded()

            assertEquals("Pengepul kepiting bakau sejak 2009.", vm.uiState.value.form.description)
        }

    @Test
    fun `a store without coordinates starts with no pin and the map at Jakarta Utara`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE.copy(lat = null, lng = null)))

            val vm = loaded()

            assertNull(vm.uiState.value.form.pin)
            assertEquals(GeoPoint.JAKARTA_UTARA, vm.uiState.value.camera.target)
        }

    @Test
    fun `no connection shows the offline state, and trying again fills the form`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.NetworkError, found(FakeStoreRepository.OWNED_STORE))

            val vm = loaded()
            assertEquals(ConnectionTrouble.NoInternet, vm.uiState.value.trouble)

            vm.onEvent(EditStoreEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.uiState.value.trouble)
            assertEquals("UD Kepiting Pak Budi", vm.uiState.value.form.name)
        }

    @Test
    fun `a server outage on loading is the server's trouble`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.ServerError)

            val vm = loaded()

            assertEquals(ConnectionTrouble.Server, vm.uiState.value.trouble)
        }

    // --- Saving -----------------------------------------------------------

    @Test
    fun `saving a new name and pin puts every field and keeps the reminder time`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.Name, "UD Kepiting Bu Sari"))
            vm.onEvent(EditStoreEvent.PinMoved(GeoPoint(-6.10812345, 106.77698765)))

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val sent = stores.updateCalls.single()
            assertEquals("UD Kepiting Bu Sari", sent.name)
            assertEquals(BigDecimal("-6.1081235"), sent.lat)
            assertEquals(BigDecimal("106.7769877"), sent.lng)
            assertEquals("081234567890", sent.phone)
            assertEquals("BCA", sent.bankName)
            assertEquals("7700112233", sent.bankAccountNo)
            assertEquals("Budi Santoso", sent.bankAccountName)
            // The form has no reminder field; the time set in Pengaturan must not be lost.
            assertEquals(LocalTime.of(6, 0), sent.stockReminderTime)
            assertEquals(EditStoreResult.Saved, vm.uiState.value.result)
        }

    @Test
    fun `saving an untouched form sends the store as it is`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val sent = stores.updateCalls.single()
            assertEquals(BigDecimal("-6.1067000"), sent.lat)
            assertEquals(BigDecimal("106.7740000"), sent.lng)
            assertNull(sent.description)
        }

    @Test
    fun `a wrong field is refused on the device`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.Name, "  "))
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.BankAccountNo, "77 001"))

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val errors = vm.uiState.value.form.errors
            assertEquals(UiText.Resource(R.string.store_error_name_required), errors[StoreField.Name])
            assertEquals(UiText.Resource(R.string.store_error_bank_account_no_invalid), errors[StoreField.BankAccountNo])
            assertTrue(stores.updateCalls.isEmpty())
        }

    @Test
    fun `a store without a pin cannot be saved until the map reports one`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE.copy(lat = null, lng = null)))
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.store_error_pin_required), vm.uiState.value.form.errors[StoreField.Pin])
            assertTrue(stores.updateCalls.isEmpty())
        }

    @Test
    fun `a phone the server refuses shows under the phone field`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.Invalid(mapOf("phone" to listOf("The phone field format is invalid.")), null)
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(UiText.Resource(R.string.register_error_phone_invalid), state.form.errors[StoreField.Phone])
            assertNull(state.result)
            assertFalse(state.isSaving)
        }

    @Test
    fun `a 403 closes the form and tells Pengaturan the seller is not the owner`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.Forbidden("This action is unauthorized.")
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            assertEquals(EditStoreResult.NotOwner, vm.uiState.value.result)
        }

    @Test
    fun `no connection while saving keeps the form and allows another try`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.NetworkError
            val vm = loaded()
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.City, "Jakarta Barat"))

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(UiText.Resource(R.string.error_network), state.generalError)
            assertEquals("Jakarta Barat", state.form.city)
            assertFalse(state.isSaving)
            assertNull(state.result)
        }

    @Test
    fun `a server outage while saving shows the server message`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.ServerError
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_server), vm.uiState.value.generalError)
        }

    @Test
    fun `pressing save twice quickly sends one request`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            stores.updateGate = gate
            val vm = loaded()

            vm.onEvent(EditStoreEvent.Save)
            runCurrent()
            assertTrue(vm.uiState.value.isSaving)
            vm.onEvent(EditStoreEvent.Save)
            gate.complete(Unit)
            advanceUntilIdle()

            assertEquals(1, stores.updateCalls.size)
        }

    // --- The map ----------------------------------------------------------

    @Test
    fun `a granted permission moves the map and the pin to the device`() =
        runTest(main.dispatcher) {
            location.point = GeoPoint(-6.2, 106.8)
            val vm = loaded()

            vm.onEvent(EditStoreEvent.LocationGranted)
            advanceUntilIdle()

            assertEquals(GeoPoint(-6.2, 106.8), vm.uiState.value.form.pin)
            assertEquals(GeoPoint(-6.2, 106.8), vm.uiState.value.camera.target)
        }

    @Test
    fun `a denied permission keeps the store's pin`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(EditStoreEvent.LocationDenied)

            assertEquals(GeoPoint(-6.1067, 106.774), vm.uiState.value.form.pin)
            assertEquals(UiText.Resource(R.string.store_location_denied), vm.uiState.value.locationMessage)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the bank account stays out of the saved state`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            val vm = loaded(handle)
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.BankAccountNo, "9876543210"))
            vm.onEvent(EditStoreEvent.FieldChanged(StoreField.BankAccountName, "Siti Rahayu"))
            vm.onEvent(EditStoreEvent.Save)
            advanceUntilIdle()

            val stored = handle.keys().map { key -> handle.get<Any?>(key)?.toString().orEmpty() }
            assertFalse(handle.keys().any { it.contains("bank", ignoreCase = true) })
            assertFalse(stored.any { it.contains("9876543210") || it.contains("7700112233") || it.contains("Siti Rahayu") })
        }
}
