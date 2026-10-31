package io.github.saptooaji.seafood.seller.feature.shipping.ui

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OJOL
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_PER_KM
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.PICKUP
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository
import io.github.saptooaji.seafood.seller.feature.store.FakeStoreRepository.Companion.found
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

/** Cara terima pesanan (#246): the list, the active switches, and adding a manual kind. */
@OptIn(ExperimentalCoroutinesApi::class)
class ShippingMethodsViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val methods = FakeShippingMethodRepository()
    private val stores = FakeStoreRepository().apply { answerCurrent(found(FakeStoreRepository.OWNED_STORE)) }

    private fun TestScope.loaded(): ShippingMethodsViewModel {
        val vm = ShippingMethodsViewModel(methods, stores)
        advanceUntilIdle()
        return vm
    }

    private fun ShippingMethodsViewModel.row(type: ShippingType) = uiState.value.rows.single { it.type == type }

    // --- The list ---------------------------------------------------------

    @Test
    fun `every kind shows once, in a fixed order, with the method when the store has it`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            val state = vm.uiState.value
            assertFalse(state.isLoading)
            assertEquals(
                listOf(ShippingType.Pickup, ShippingType.OwnDelivery, ShippingType.Ojol, ShippingType.Expedition),
                state.rows.map { it.type },
            )
            assertEquals(PICKUP, vm.row(ShippingType.Pickup).method)
            assertEquals(OWN_PER_KM, vm.row(ShippingType.OwnDelivery).method)
            assertNull(vm.row(ShippingType.Ojol).method)
            assertNull(vm.row(ShippingType.Expedition).method)
            assertTrue(state.isOwner)
        }

    @Test
    fun `an older store without own delivery offers to add it`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))

            val vm = loaded()

            assertNull(vm.row(ShippingType.OwnDelivery).method)
        }

    @Test
    fun `a staff member sees the list but is not the owner`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))

            val vm = loaded()

            assertFalse(vm.uiState.value.isOwner)
            assertEquals(PICKUP, vm.row(ShippingType.Pickup).method)
        }

    @Test
    fun `no connection shows the offline state, and trying again loads`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.NetworkError, ApiResult.Success(FakeShippingMethodRepository.DEFAULT))

            val vm = loaded()
            assertEquals(ConnectionTrouble.NoInternet, vm.uiState.value.trouble)

            vm.onEvent(ShippingMethodsEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.uiState.value.trouble)
            assertEquals(OWN_PER_KM, vm.row(ShippingType.OwnDelivery).method)
        }

    @Test
    fun `a server outage is the server's trouble`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.ServerError)

            val vm = loaded()

            assertEquals(ConnectionTrouble.Server, vm.uiState.value.trouble)
        }

    @Test
    fun `coming back from the own delivery form loads the list again`() =
        runTest(main.dispatcher) {
            val vm = loaded()
            methods.answerList(ApiResult.Success(listOf(PICKUP, FakeShippingMethodRepository.OWN_FLAT)))

            vm.onEvent(ShippingMethodsEvent.Reload)
            advanceUntilIdle()

            assertEquals(FakeShippingMethodRepository.OWN_FLAT, vm.row(ShippingType.OwnDelivery).method)
        }

    // --- The active switch ------------------------------------------------

    @Test
    fun `turning pickup off while own delivery is on puts every field of pickup with is_active false`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            advanceUntilIdle()

            assertEquals(
                listOf(
                    1L to
                        ShippingMethodInput(
                            type = ShippingType.Pickup,
                            feeType = ShippingFeeType.Flat,
                            flatFee = 0,
                            isActive = false,
                        ),
                ),
                methods.updateCalls,
            )
            assertFalse(vm.row(ShippingType.Pickup).method!!.isActive)
            assertNull(vm.uiState.value.notice)
        }

    @Test
    fun `turning own delivery off keeps its fees, since PUT writes every fee column`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.OwnDelivery, active = false))
            advanceUntilIdle()

            val sent = methods.updateCalls.single().second
            assertEquals(ShippingFeeType.PerKm, sent.feeType)
            assertEquals(BigDecimal("20.00"), sent.maxRadiusKm)
            assertEquals(3_000L, sent.feePerKm)
            assertEquals(10_000L, sent.minFee)
            assertEquals(false, sent.isActive)
        }

    @Test
    fun `the switch moves at once, before the server answers`() =
        runTest(main.dispatcher) {
            methods.updateGates.add(CompletableDeferred())
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            runCurrent()

            assertFalse(vm.row(ShippingType.Pickup).method!!.isActive)
        }

    @Test
    fun `turning off the last active method puts the switch back on and shows the server's message`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            methods.updateResults.add(ApiResult.Invalid(mapOf("is_active" to listOf(LAST_ACTIVE)), LAST_ACTIVE))
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            advanceUntilIdle()

            assertTrue(vm.row(ShippingType.Pickup).method!!.isActive)
            assertEquals(ShippingNotice.Refused(LAST_ACTIVE), vm.uiState.value.notice)
        }

    @Test
    fun `no connection puts the switch back and says why`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.NetworkError)
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.OwnDelivery, active = false))
            advanceUntilIdle()

            assertTrue(vm.row(ShippingType.OwnDelivery).method!!.isActive)
            assertEquals(ShippingNotice.NoInternet, vm.uiState.value.notice)
        }

    @Test
    fun `a 403 from a stale owner view says only the owner may change it and loads again`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.Forbidden("This action is unauthorized."))
            val vm = loaded()
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            advanceUntilIdle()

            assertEquals(ShippingNotice.OwnerOnly, vm.uiState.value.notice)
            assertTrue(vm.row(ShippingType.Pickup).method!!.isActive)
            assertEquals(2, methods.listCalls)
            assertFalse(vm.uiState.value.isOwner)
        }

    @Test
    fun `a staff member cannot flip a switch`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            advanceUntilIdle()

            assertTrue(methods.updateCalls.isEmpty())
            assertTrue(vm.row(ShippingType.Pickup).method!!.isActive)
        }

    @Test
    fun `two switches flipped quickly are sent one after the other, in order`() =
        runTest(main.dispatcher) {
            val first = CompletableDeferred<Unit>()
            val second = CompletableDeferred<Unit>()
            methods.updateGates.addAll(listOf(first, second))
            methods.answerList(ApiResult.Success(listOf(PICKUP, OWN_PER_KM, OJOL)))
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Ojol, active = false))
            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            runCurrent()
            // The second waits until the server has answered the first.
            assertEquals(listOf(OJOL.id), methods.updateCalls.map { it.first })

            first.complete(Unit)
            runCurrent()
            assertEquals(listOf(OJOL.id, PICKUP.id), methods.updateCalls.map { it.first })

            second.complete(Unit)
            advanceUntilIdle()
            assertFalse(vm.row(ShippingType.Ojol).method!!.isActive)
            assertFalse(vm.row(ShippingType.Pickup).method!!.isActive)
        }

    @Test
    fun `a kind the store does not have has no switch to flip`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Expedition, active = true))
            advanceUntilIdle()

            assertTrue(methods.updateCalls.isEmpty())
        }

    // --- Adding a manual kind ---------------------------------------------

    @Test
    fun `adding ojol asks first, then posts it with the manual fee`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Ojol))
            assertEquals(ShippingType.Ojol, vm.uiState.value.adding)
            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            advanceUntilIdle()

            assertEquals(listOf(ShippingMethodInput(type = ShippingType.Ojol, feeType = ShippingFeeType.Manual)), methods.addCalls)
            assertNull(vm.uiState.value.adding)
            val added = vm.row(ShippingType.Ojol).method!!
            assertTrue(added.isActive)
            assertEquals(FakeShippingMethodRepository.NEW_ID, added.id)
        }

    @Test
    fun `adding expedition posts the manual fee too`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Expedition))
            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            advanceUntilIdle()

            assertEquals(ShippingType.Expedition, methods.addCalls.single().type)
            assertEquals(ShippingFeeType.Manual, methods.addCalls.single().feeType)
        }

    @Test
    fun `cancelling the sheet adds nothing`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Ojol))
            vm.onEvent(ShippingMethodsEvent.CloseAdd)
            advanceUntilIdle()

            assertNull(vm.uiState.value.adding)
            assertTrue(methods.addCalls.isEmpty())
        }

    @Test
    fun `own delivery is added in its own form, not the sheet`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.OwnDelivery))

            assertNull(vm.uiState.value.adding)
        }

    @Test
    fun `pressing add twice quickly sends one request`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            methods.addGate = gate
            val vm = loaded()
            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Ojol))

            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            runCurrent()
            assertTrue(vm.uiState.value.isAdding)
            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            gate.complete(Unit)
            advanceUntilIdle()

            assertEquals(1, methods.addCalls.size)
        }

    @Test
    fun `a staff member cannot add`() =
        runTest(main.dispatcher) {
            stores.answerCurrent(found(FakeStoreRepository.STAFF_STORE))
            val vm = loaded()

            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Ojol))
            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            advanceUntilIdle()

            assertNull(vm.uiState.value.adding)
            assertTrue(methods.addCalls.isEmpty())
        }

    @Test
    fun `no connection while adding keeps the sheet open and says why`() =
        runTest(main.dispatcher) {
            methods.addResult = ApiResult.NetworkError
            val vm = loaded()
            vm.onEvent(ShippingMethodsEvent.Add(ShippingType.Ojol))

            vm.onEvent(ShippingMethodsEvent.ConfirmAdd)
            advanceUntilIdle()

            assertEquals(ShippingType.Ojol, vm.uiState.value.adding)
            assertEquals(ShippingNotice.NoInternet, vm.uiState.value.notice)
            assertNull(vm.row(ShippingType.Ojol).method)
        }

    @Test
    fun `a shown notice goes away`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.ServerError)
            val vm = loaded()
            vm.onEvent(ShippingMethodsEvent.Toggle(ShippingType.Pickup, active = false))
            advanceUntilIdle()
            assertEquals(ShippingNotice.Server, vm.uiState.value.notice)

            vm.onEvent(ShippingMethodsEvent.NoticeShown)

            assertNull(vm.uiState.value.notice)
        }

    private companion object {
        const val LAST_ACTIVE = "Toko harus punya minimal satu cara kirim yang aktif."
    }
}
