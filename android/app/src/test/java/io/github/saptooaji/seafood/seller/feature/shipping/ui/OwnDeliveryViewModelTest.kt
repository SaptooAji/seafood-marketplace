package io.github.saptooaji.seafood.seller.feature.shipping.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_FLAT
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_PER_KM
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.PICKUP
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
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
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.Arguments
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource
import java.math.BigDecimal

/** The Antar sendiri form (#246): adding it, or changing its radius and fees. */
@OptIn(ExperimentalCoroutinesApi::class)
class OwnDeliveryViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val methods = FakeShippingMethodRepository()

    private fun TestScope.loaded(): OwnDeliveryViewModel {
        val vm = OwnDeliveryViewModel(methods)
        advanceUntilIdle()
        return vm
    }

    private fun OwnDeliveryViewModel.type(
        field: OwnDeliveryField,
        value: String,
    ) = onEvent(OwnDeliveryEvent.FieldChanged(field, value))

    // --- Loading ----------------------------------------------------------

    @Test
    fun `an existing per km method fills the form`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            val state = vm.uiState.value
            assertFalse(state.isLoading)
            assertFalse(state.isNew)
            val form = state.form
            assertEquals("20", form.radius)
            assertEquals(ShippingFeeType.PerKm, form.feeType)
            assertEquals("3000", form.feePerKm)
            assertEquals("10000", form.minFee)
            assertEquals("", form.flatFee)
            assertTrue(form.isActive)
        }

    @Test
    fun `a radius with decimals is shown with a comma`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP, OWN_PER_KM.copy(maxRadiusKm = BigDecimal("7.50")))))

            val vm = loaded()

            assertEquals("7,5", vm.uiState.value.form.radius)
        }

    @Test
    fun `a store without own delivery starts a new one with a flat fee`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))

            val vm = loaded()

            val state = vm.uiState.value
            assertTrue(state.isNew)
            assertEquals(ShippingFeeType.Flat, state.form.feeType)
            assertEquals("", state.form.radius)
            assertTrue(state.form.isActive)
        }

    @Test
    fun `no connection shows the offline state, and trying again fills the form`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.NetworkError, ApiResult.Success(FakeShippingMethodRepository.DEFAULT))

            val vm = loaded()
            assertEquals(ConnectionTrouble.NoInternet, vm.uiState.value.trouble)

            vm.onEvent(OwnDeliveryEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.uiState.value.trouble)
            assertEquals("20", vm.uiState.value.form.radius)
        }

    // --- Adding -----------------------------------------------------------

    @Test
    fun `adding a flat fee of 10 000 within 10 km posts the radius and the flat fee only`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()
            vm.type(OwnDeliveryField.Radius, "10")
            vm.type(OwnDeliveryField.FlatFee, "10.000")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            assertEquals(
                listOf(
                    ShippingMethodInput(
                        type = ShippingType.OwnDelivery,
                        feeType = ShippingFeeType.Flat,
                        maxRadiusKm = BigDecimal("10"),
                        flatFee = 10_000,
                    ),
                ),
                methods.addCalls,
            )
            assertTrue(methods.updateCalls.isEmpty())
            assertEquals(OwnDeliveryResult.Saved, vm.uiState.value.result)
        }

    @Test
    fun `adding 3 000 per km with a 10 000 minimum within 20 km sends no flat fee`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()
            vm.type(OwnDeliveryField.Radius, "20")
            vm.onEvent(OwnDeliveryEvent.FeeTypePicked(ShippingFeeType.PerKm))
            vm.type(OwnDeliveryField.FeePerKm, "3000")
            vm.type(OwnDeliveryField.MinFee, "10000")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            val sent = methods.addCalls.single()
            assertEquals(ShippingFeeType.PerKm, sent.feeType)
            assertEquals(3_000L, sent.feePerKm)
            assertEquals(10_000L, sent.minFee)
            assertNull(sent.flatFee)
        }

    @Test
    fun `a radius typed with a comma is sent with a dot`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()
            vm.type(OwnDeliveryField.Radius, "7,5")
            vm.type(OwnDeliveryField.FlatFee, "5000")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            assertEquals(
                "7.5",
                methods.addCalls
                    .single()
                    .maxRadiusKm!!
                    .toPlainString(),
            )
        }

    @Test
    fun `a free flat fee is allowed`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()
            vm.type(OwnDeliveryField.Radius, "3")
            vm.type(OwnDeliveryField.FlatFee, "0")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            assertEquals(0L, methods.addCalls.single().flatFee)
        }

    // --- Editing ----------------------------------------------------------

    @Test
    fun `switching from flat to per km puts the new fees, the active flag, and no flat fee`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP, OWN_FLAT)))
            val vm = loaded()
            vm.onEvent(OwnDeliveryEvent.FeeTypePicked(ShippingFeeType.PerKm))
            vm.type(OwnDeliveryField.FeePerKm, "3.000")
            vm.type(OwnDeliveryField.MinFee, "10.000")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            val (id, sent) = methods.updateCalls.single()
            assertEquals(OWN_FLAT.id, id)
            assertEquals(
                ShippingMethodInput(
                    type = ShippingType.OwnDelivery,
                    feeType = ShippingFeeType.PerKm,
                    maxRadiusKm = BigDecimal("10"),
                    feePerKm = 3_000,
                    minFee = 10_000,
                    isActive = true,
                ),
                sent,
            )
            assertTrue(methods.addCalls.isEmpty())
        }

    @Test
    fun `turning it off in the form is sent with the save`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(OwnDeliveryEvent.ActiveChanged(false))
            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            assertEquals(
                false,
                methods.updateCalls
                    .single()
                    .second.isActive,
            )
        }

    @Test
    fun `the fees of the other fee type are kept in the form while switching back and forth`() =
        runTest(main.dispatcher) {
            val vm = loaded()

            vm.onEvent(OwnDeliveryEvent.FeeTypePicked(ShippingFeeType.Flat))
            vm.onEvent(OwnDeliveryEvent.FeeTypePicked(ShippingFeeType.PerKm))

            assertEquals("3000", vm.uiState.value.form.feePerKm)
            assertEquals("10000", vm.uiState.value.form.minFee)
        }

    // --- Refused on the device --------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("wrongForms")
    fun `a wrong form is refused on the device`(
        case: String,
        feeType: ShippingFeeType,
        values: Map<OwnDeliveryField, String>,
        field: OwnDeliveryField,
        error: Int,
    ) = runTest(main.dispatcher) {
        methods.answerList(ApiResult.Success(listOf(PICKUP)))
        val vm = loaded()
        vm.onEvent(OwnDeliveryEvent.FeeTypePicked(feeType))
        values.forEach { (f, v) -> vm.type(f, v) }

        vm.onEvent(OwnDeliveryEvent.Save)
        advanceUntilIdle()

        assertEquals(UiText.Resource(error), vm.uiState.value.form.errors[field], case)
        assertTrue(methods.addCalls.isEmpty(), case)
    }

    @Test
    fun `typing again clears the error of that field`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP)))
            val vm = loaded()
            vm.onEvent(OwnDeliveryEvent.Save)

            vm.type(OwnDeliveryField.Radius, "5")

            assertNull(vm.uiState.value.form.errors[OwnDeliveryField.Radius])
        }

    // --- Refused by the server --------------------------------------------

    @Test
    fun `a fee the server refuses shows its message under that field`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.Invalid(mapOf("fee_per_km" to listOf(PER_KM_REFUSED)), PER_KM_REFUSED))
            val vm = loaded()

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(UiText.Raw(PER_KM_REFUSED), state.form.errors[OwnDeliveryField.FeePerKm])
            assertNull(state.result)
            assertFalse(state.isSaving)
        }

    @Test
    fun `turning off the last active method in the form shows the message at the switch and turns it back on`() =
        runTest(main.dispatcher) {
            methods.answerList(ApiResult.Success(listOf(PICKUP.copy(isActive = false), OWN_PER_KM)))
            methods.updateResults.add(ApiResult.Invalid(mapOf("is_active" to listOf(LAST_ACTIVE)), LAST_ACTIVE))
            val vm = loaded()
            vm.onEvent(OwnDeliveryEvent.ActiveChanged(false))

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            val form = vm.uiState.value.form
            assertEquals(UiText.Raw(LAST_ACTIVE), form.errors[OwnDeliveryField.Active])
            assertTrue(form.isActive)
        }

    @Test
    fun `a 403 closes the form and says the seller is not the owner`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.Forbidden("This action is unauthorized."))
            val vm = loaded()

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            assertEquals(OwnDeliveryResult.NotOwner, vm.uiState.value.result)
        }

    @Test
    fun `no connection while saving keeps the form and allows another try`() =
        runTest(main.dispatcher) {
            methods.updateResults.add(ApiResult.NetworkError)
            val vm = loaded()
            vm.type(OwnDeliveryField.Radius, "25")

            vm.onEvent(OwnDeliveryEvent.Save)
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(UiText.Resource(R.string.error_network), state.generalError)
            assertEquals("25", state.form.radius)
            assertFalse(state.isSaving)
        }

    @Test
    fun `pressing save twice quickly sends one request`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            methods.updateGates.add(gate)
            val vm = loaded()

            vm.onEvent(OwnDeliveryEvent.Save)
            runCurrent()
            assertTrue(vm.uiState.value.isSaving)
            vm.onEvent(OwnDeliveryEvent.Save)
            gate.complete(Unit)
            advanceUntilIdle()

            assertEquals(1, methods.updateCalls.size)
        }

    private companion object {
        const val PER_KM_REFUSED = "Tarif per km wajib diisi dan lebih dari 0."
        const val LAST_ACTIVE = "Toko harus punya minimal satu cara kirim yang aktif."

        private val FLAT_OK = mapOf(OwnDeliveryField.Radius to "10", OwnDeliveryField.FlatFee to "10000")
        private val PER_KM_OK =
            mapOf(OwnDeliveryField.Radius to "20", OwnDeliveryField.FeePerKm to "3000", OwnDeliveryField.MinFee to "10000")

        @JvmStatic
        fun wrongForms(): List<Arguments> =
            listOf(
                flat("no radius", OwnDeliveryField.Radius, null, R.string.shipping_error_radius),
                flat("radius 0", OwnDeliveryField.Radius, "0", R.string.shipping_error_radius),
                flat("radius over 9999,99", OwnDeliveryField.Radius, "10000", R.string.shipping_error_radius_max),
                flat("radius with three decimals", OwnDeliveryField.Radius, "1,234", R.string.shipping_error_radius),
                flat("radius not a number", OwnDeliveryField.Radius, "jauh", R.string.shipping_error_radius),
                flat("no flat fee", OwnDeliveryField.FlatFee, null, R.string.shipping_error_fee),
                flat("flat fee over 100 000 000", OwnDeliveryField.FlatFee, "100.000.001", R.string.shipping_error_fee),
                perKm("no fee per km", OwnDeliveryField.FeePerKm, null, R.string.shipping_error_fee_per_km),
                perKm("fee per km of 0", OwnDeliveryField.FeePerKm, "0", R.string.shipping_error_fee_per_km),
                perKm("no minimum", OwnDeliveryField.MinFee, null, R.string.shipping_error_fee),
                perKm("fee with a decimal", OwnDeliveryField.MinFee, "1,5", R.string.shipping_error_fee),
            )

        /** A flat form that is right except [field], typed as [value] or left empty when null. */
        private fun flat(
            case: String,
            field: OwnDeliveryField,
            value: String?,
            error: Int,
        ) = arguments(case, ShippingFeeType.Flat, FLAT_OK.with(field, value), field, error)

        /** A per km form that is right except [field], typed as [value] or left empty when null. */
        private fun perKm(
            case: String,
            field: OwnDeliveryField,
            value: String?,
            error: Int,
        ) = arguments(case, ShippingFeeType.PerKm, PER_KM_OK.with(field, value), field, error)

        private fun Map<OwnDeliveryField, String>.with(
            field: OwnDeliveryField,
            value: String?,
        ) = if (value == null) this - field else this + (field to value)
    }
}
