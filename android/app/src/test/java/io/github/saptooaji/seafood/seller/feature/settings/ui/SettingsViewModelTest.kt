package io.github.saptooaji.seafood.seller.feature.settings.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.FakeAuthRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import io.github.saptooaji.seafood.seller.testing.MainDispatcherExtension
import kotlinx.coroutines.CompletableDeferred
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.advanceUntilIdle
import kotlinx.coroutines.test.runCurrent
import kotlinx.coroutines.test.runTest
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNotNull
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.junit.jupiter.api.extension.RegisterExtension
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource
import java.time.LocalTime

/** The Pengaturan tab (#245): profile, store, reminder, and signing out. */
@OptIn(ExperimentalCoroutinesApi::class)
class SettingsViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val auth = FakeAuthRepository()
    private val stores = FakeStoreRepository().apply { answerCurrent(found(FakeStoreRepository.OWNED_STORE)) }

    private fun viewModel() = SettingsViewModel(auth, stores)

    // --- Loading ----------------------------------------------------------

    @Test
    fun `the owner sees the profile, the store, the bank account, and the reminder`() =
        runTest(main.dispatcher) {
            val vm = viewModel()
            advanceUntilIdle()

            val state = vm.uiState.value
            assertFalse(state.isLoading)
            assertNull(state.trouble)
            assertEquals(FakeAuthRepository.BUDI, state.user)
            assertEquals("UD Kepiting Pak Budi", state.store!!.name)
            assertEquals("Aktif", state.store!!.statusLabel)
            assertEquals("Jakarta Utara", state.store!!.city)
            assertEquals(StoreRole.Owner, state.store!!.role)
            assertEquals("BCA", state.store!!.bank!!.name)
            assertEquals(LocalTime.of(6, 0), state.store!!.stockReminderTime)
            assertTrue(state.isOwner)
        }

    @Test
    fun `a staff member gets no bank account and cannot edit the store`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(StoreRole.Staff, state.store!!.role)
            assertNull(state.store!!.bank)
            assertFalse(state.isOwner)
        }

    @Test
    fun `the role decides, not whether a bank account came`() =
        runTest(main.dispatcher) {
            // An owner's store always has a bank account; a missing one must not make the owner staff.
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE.copy(bank = null)))

            val vm = viewModel()
            advanceUntilIdle()

            assertTrue(vm.uiState.value.isOwner)
        }

    @Test
    fun `a pending store can still be opened and edited`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.PENDING_STORE))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(
                "Menunggu Persetujuan",
                vm.uiState.value.store!!
                    .statusLabel,
            )
            assertTrue(vm.uiState.value.isOwner)
        }

    @Test
    fun `a user without an avatar or a phone is still shown`() =
        runTest(main.dispatcher) {
            auth.meResult = ApiResult.Success(FakeAuthRepository.BUDI.copy(phone = null, avatar = null))

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(
                "Budi Santoso",
                vm.uiState.value.user!!
                    .name,
            )
            assertNull(
                vm.uiState.value.user!!
                    .phone,
            )
        }

    @Test
    fun `no connection shows the offline state, and trying again loads`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.NetworkError, found(FakeStoreRepository.OWNED_STORE))

            val vm = viewModel()
            advanceUntilIdle()
            assertEquals(ConnectionTrouble.NoInternet, vm.uiState.value.trouble)

            vm.onEvent(SettingsEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.uiState.value.trouble)
            assertEquals(
                "UD Kepiting Pak Budi",
                vm.uiState.value.store!!
                    .name,
            )
        }

    @Test
    fun `a server outage on the profile is the server's trouble`() =
        runTest(main.dispatcher) {
            auth.meResult = ApiResult.ServerError

            val vm = viewModel()
            advanceUntilIdle()

            assertEquals(ConnectionTrouble.Server, vm.uiState.value.trouble)
        }

    // --- Profile ------------------------------------------------------------

    @Test
    fun `editing the profile starts from the name and phone of the account`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.EditProfile)

            val profile = vm.uiState.value.profile!!
            assertEquals("Budi Santoso", profile.name)
            assertEquals("081234567890", profile.phone)
            assertTrue(profile.errors.isEmpty())
        }

    @Test
    fun `an account without a phone starts with an empty phone field`() =
        runTest(main.dispatcher) {
            auth.meResult = ApiResult.Success(FakeAuthRepository.BUDI.copy(phone = null))
            val vm = loaded()

            vm.onEvent(SettingsEvent.EditProfile)

            assertEquals(
                "",
                vm.uiState.value.profile!!
                    .phone,
            )
        }

    @Test
    fun `saving the profile sends the trimmed name and the phone without spaces, then shows the answer`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)
            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Name, "  Budi S.  "))
            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Phone, "0812-9999 8888"))

            vm.onEvent(SettingsEvent.SaveProfile)
            advanceUntilIdle()

            assertEquals(listOf("Budi S." to "081299998888"), auth.updateProfileCalls)
            assertEquals(
                "Budi S.",
                vm.uiState.value.user!!
                    .name,
            )
            assertEquals(
                "081299998888",
                vm.uiState.value.user!!
                    .phone,
            )
            assertNull(vm.uiState.value.profile)
        }

    @ParameterizedTest(name = "{0}")
    @MethodSource("wrongProfiles")
    fun `a wrong profile is refused on the device`(
        case: String,
        name: String,
        phone: String,
        field: ProfileField,
        error: Int,
    ) = runTest(main.dispatcher) {
        val vm = loaded()
        vm.onEvent(SettingsEvent.EditProfile)
        vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Name, name))
        vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Phone, phone))

        vm.onEvent(SettingsEvent.SaveProfile)
        advanceUntilIdle()

        assertEquals(
            UiText.Resource(error),
            vm.uiState.value.profile!!
                .errors[field],
            case,
        )
        assertTrue(auth.updateProfileCalls.isEmpty())
    }

    @Test
    fun `typing again clears the error of that field`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)
            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Phone, "12345"))
            vm.onEvent(SettingsEvent.SaveProfile)

            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Phone, "0812"))

            assertNull(
                vm.uiState.value.profile!!
                    .errors[ProfileField.Phone],
            )
        }

    @Test
    fun `a phone the server refuses shows under the phone field and keeps the sheet open`() =
        runTest(main.dispatcher) {
            auth.updateProfileResult = ApiResult.Invalid(mapOf("phone" to listOf("The phone field format is invalid.")), null)
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)

            vm.onEvent(SettingsEvent.SaveProfile)
            advanceUntilIdle()

            val profile = vm.uiState.value.profile!!
            assertEquals(UiText.Resource(R.string.register_error_phone_invalid), profile.errors[ProfileField.Phone])
            assertFalse(profile.isSaving)
            assertEquals(
                "Budi Santoso",
                vm.uiState.value.user!!
                    .name,
            )
        }

    @Test
    fun `no connection while saving the profile keeps what was typed`() =
        runTest(main.dispatcher) {
            auth.updateProfileResult = ApiResult.NetworkError
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)
            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Name, "Budi S."))

            vm.onEvent(SettingsEvent.SaveProfile)
            advanceUntilIdle()

            val profile = vm.uiState.value.profile!!
            assertEquals("Budi S.", profile.name)
            assertEquals(UiText.Resource(R.string.error_network), profile.generalError)
        }

    @Test
    fun `pressing save twice quickly sends one request`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            auth.updateProfileGate = gate
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)

            vm.onEvent(SettingsEvent.SaveProfile)
            runCurrent()
            assertTrue(
                vm.uiState.value.profile!!
                    .isSaving,
            )
            vm.onEvent(SettingsEvent.SaveProfile)
            gate.complete(Unit)
            advanceUntilIdle()

            assertEquals(1, auth.updateProfileCalls.size)
        }

    @Test
    fun `closing the profile sheet sends nothing`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            vm.onEvent(SettingsEvent.EditProfile)
            vm.onEvent(SettingsEvent.ProfileChanged(ProfileField.Name, "Budi S."))

            vm.onEvent(SettingsEvent.CloseProfile)

            assertNull(vm.uiState.value.profile)
            assertTrue(auth.updateProfileCalls.isEmpty())
            assertEquals(
                "Budi Santoso",
                vm.uiState.value.user!!
                    .name,
            )
        }

    // --- Stock reminder -----------------------------------------------------

    @Test
    fun `setting the reminder to 06 30 puts the whole store with the new time`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.SaveReminder(LocalTime.of(6, 30)))
            advanceUntilIdle()

            val sent = stores.updateCalls.single()
            assertEquals(LocalTime.of(6, 30), sent.stockReminderTime)
            // PUT seller/store takes every field, so the rest goes as it is on the server.
            val store = FakeStoreRepository.OWNED_STORE
            assertEquals(store.name, sent.name)
            assertEquals(store.phone, sent.phone)
            assertEquals(store.address, sent.address)
            assertEquals(store.city, sent.city)
            assertEquals(store.lat, sent.lat)
            assertEquals(store.lng, sent.lng)
            assertEquals(store.bank!!.name, sent.bankName)
            assertEquals(store.bank!!.accountNo, sent.bankAccountNo)
            assertEquals(store.bank!!.accountName, sent.bankAccountName)
            assertEquals(
                LocalTime.of(6, 30),
                vm.uiState.value.store!!
                    .stockReminderTime,
            )
            assertFalse(vm.uiState.value.isSavingReminder)
        }

    @Test
    fun `removing the reminder sends no time`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.SaveReminder(null))
            advanceUntilIdle()

            assertNull(stores.updateCalls.single().stockReminderTime)
            assertNull(
                vm.uiState.value.store!!
                    .stockReminderTime,
            )
        }

    @Test
    fun `a staff member cannot set the reminder`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))
            val vm = loaded()

            vm.onEvent(SettingsEvent.SaveReminder(LocalTime.of(6, 30)))
            advanceUntilIdle()

            assertTrue(stores.updateCalls.isEmpty())
        }

    @Test
    fun `a 403 on the reminder says only the owner may change it and loads the store again`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.Forbidden("This action is unauthorized.")
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE), found(FakeStoreRepository.STAFF_STORE))
            val vm = loaded()

            vm.onEvent(SettingsEvent.SaveReminder(LocalTime.of(6, 30)))
            advanceUntilIdle()

            assertEquals(SettingsNotice.OwnerOnly, vm.uiState.value.notice)
            assertEquals(2, stores.currentCalls)
            assertFalse(vm.uiState.value.isOwner)
        }

    @Test
    fun `no connection while setting the reminder keeps the old time and says why`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.NetworkError
            val vm = loaded()

            vm.onEvent(SettingsEvent.SaveReminder(LocalTime.of(6, 30)))
            advanceUntilIdle()

            assertEquals(SettingsNotice.NoInternet, vm.uiState.value.notice)
            assertEquals(
                LocalTime.of(6, 0),
                vm.uiState.value.store!!
                    .stockReminderTime,
            )
        }

    @Test
    fun `a shown notice goes away`() =
        runTest(main.dispatcher) {
            stores.updateResult = ApiResult.ServerError
            val vm = loaded()
            vm.onEvent(SettingsEvent.SaveReminder(LocalTime.of(6, 30)))
            advanceUntilIdle()
            assertEquals(SettingsNotice.Server, vm.uiState.value.notice)

            vm.onEvent(SettingsEvent.NoticeShown)

            assertNull(vm.uiState.value.notice)
        }

    // --- Coming back from the store form -------------------------------------

    @Test
    fun `a saved store form loads the store again`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            stores.answerCurrent(found(FakeStoreRepository.OWNED_STORE.copy(name = "UD Kepiting Bu Sari")))

            vm.onEvent(SettingsEvent.StoreEdited(EditStoreResult.Saved))
            advanceUntilIdle()

            assertEquals(
                "UD Kepiting Bu Sari",
                vm.uiState.value.store!!
                    .name,
            )
            assertNull(vm.uiState.value.notice)
        }

    @Test
    fun `a store form refused with 403 says only the owner may change it and shows the staff view`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))

            vm.onEvent(SettingsEvent.StoreEdited(EditStoreResult.NotOwner))
            advanceUntilIdle()

            assertEquals(SettingsNotice.OwnerOnly, vm.uiState.value.notice)
            assertFalse(vm.uiState.value.isOwner)
            assertNull(
                vm.uiState.value.store!!
                    .bank,
            )
        }

    // --- Signing out --------------------------------------------------------

    @Test
    fun `signing out asks first, and cancelling keeps the seller signed in`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.AskSignOut)
            assertTrue(vm.uiState.value.confirmingSignOut)
            vm.onEvent(SettingsEvent.CancelSignOut)
            advanceUntilIdle()

            assertFalse(vm.uiState.value.confirmingSignOut)
            assertEquals(0, auth.logoutCalls)
        }

    @Test
    fun `signing out deletes the token, forgets the store, and opens sign-in`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.AskSignOut)
            vm.onEvent(SettingsEvent.SignOut)
            advanceUntilIdle()

            assertEquals(1, auth.logoutCalls)
            assertTrue(auth.tokenCleared)
            val state = vm.uiState.value
            assertTrue(state.signedOut)
            assertNull(state.store)
            assertNull(state.user)
        }

    @Test
    fun `signing out works when the settings could not load`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(ApiResult.NetworkError)
            val vm = viewModel()
            advanceUntilIdle()
            assertNotNull(vm.uiState.value.trouble)

            vm.onEvent(SettingsEvent.AskSignOut)
            vm.onEvent(SettingsEvent.SignOut)
            advanceUntilIdle()

            assertTrue(auth.tokenCleared)
            assertTrue(vm.uiState.value.signedOut)
        }

    @Test
    fun `pressing sign out twice signs out once`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(SettingsEvent.AskSignOut)
            vm.onEvent(SettingsEvent.SignOut)
            vm.onEvent(SettingsEvent.SignOut)
            advanceUntilIdle()

            assertEquals(1, auth.logoutCalls)
        }

    // --- Security ---------------------------------------------------------

    @Test
    fun `the settings state never prints the account number`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            assertFalse(
                vm.uiState.value
                    .toString()
                    .contains("7700112233"),
            )
        }

    private fun kotlinx.coroutines.test.TestScope.loaded(): SettingsViewModel {
        val vm = viewModel()
        advanceUntilIdle()
        return vm
    }

    companion object {
        @JvmStatic
        fun wrongProfiles(): List<Arguments> =
            listOf(
                arguments("empty name", "   ", "081234567890", ProfileField.Name, R.string.register_error_name_required),
                arguments("name of 256", "a".repeat(256), "081234567890", ProfileField.Name, R.string.register_error_name_too_long),
                arguments("empty phone", "Budi Santoso", "", ProfileField.Phone, R.string.register_error_phone_invalid),
                arguments("short phone", "Budi Santoso", "12345", ProfileField.Phone, R.string.register_error_phone_invalid),
                arguments("phone not starting 08", "Budi Santoso", "0712345678", ProfileField.Phone, R.string.register_error_phone_invalid),
            )
    }
}
