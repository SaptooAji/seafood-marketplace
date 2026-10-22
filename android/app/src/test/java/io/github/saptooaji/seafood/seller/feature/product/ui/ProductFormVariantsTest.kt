package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductPhotoRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.DETAIL
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.NEW_VARIANT
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.variant
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
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

/**
 * The variants of a saved product (#239): each add, change, and delete goes
 * to the server at once, apart from the form's own Save.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ProductFormVariantsTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val products = FakeProductRepository()
    private val categories = FakeCategoryRepository()
    private val photos = FakeProductPhotoRepository()

    /** The edit form of [DETAIL], which has variants 1 and 2, loaded. */
    private fun TestScope.editForm(): ProductFormViewModel {
        val vm = ProductFormViewModel(products, categories, photos, SavedStateHandle(mapOf("productId" to DETAIL.id)))
        advanceUntilIdle()
        return vm
    }

    private val ProductFormViewModel.state get() = uiState.value

    private fun ProductFormViewModel.typeInSheet(
        field: VariantField,
        value: String,
    ) = onEvent(ProductFormEvent.VariantSheetTextChanged(field, value))

    /** "1 kg" per kg at Rp120.000, from 0,5 kg, with 10 kg in stock. */
    private fun ProductFormViewModel.fillNewVariant() {
        onEvent(ProductFormEvent.AddVariant)
        typeInSheet(VariantField.Name, "1 kg")
        onEvent(ProductFormEvent.VariantSheetUnitPicked(VariantUnit.Kg))
        typeInSheet(VariantField.Price, "120000")
        typeInSheet(VariantField.MinQty, "0,5")
        typeInSheet(VariantField.InitialStock, "10")
    }

    // --- Adding -----------------------------------------------------------

    @Test
    fun `a new variant is sent with its opening stock and shows in the list`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.fillNewVariant()

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            val (productId, sent) = products.addVariantCalls.single()
            assertEquals(DETAIL.id, productId)
            assertEquals("1 kg", sent.name)
            assertEquals(VariantUnit.Kg, sent.unit)
            assertEquals(120_000L, sent.price)
            assertEquals(BigDecimal("0.5"), sent.minQty)
            assertEquals(BigDecimal("10"), sent.initialStock)
            assertEquals(listOf("Ukuran 1", "Ukuran 2", "1 kg"), vm.state.variants.map { it.name })
            assertNull(vm.state.variantSheet)
            assertEquals(UiText.Resource(R.string.product_form_variant_added, listOf("1 kg")), vm.state.variantMessage)
        }

    @Test
    fun `an empty opening stock is sent as none`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.fillNewVariant()
            vm.typeInSheet(VariantField.InitialStock, "")

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertNull(
                products.addVariantCalls
                    .single()
                    .second.initialStock,
            )
        }

    @Test
    fun `the add sheet starts empty, per kg, from 1`() =
        runTest(main.dispatcher) {
            val vm = editForm()

            vm.onEvent(ProductFormEvent.AddVariant)

            val sheet = vm.state.variantSheet!!
            assertNull(sheet.variantId)
            assertEquals("", sheet.name)
            assertEquals(VariantUnit.Kg, sheet.unit)
            assertEquals("1", sheet.minQty)
            assertTrue(sheet.isActive)
        }

    // --- Changing ---------------------------------------------------------

    @Test
    fun `the edit sheet opens with the variant as it is`() =
        runTest(main.dispatcher) {
            products.getResult =
                ApiResult.Success(DETAIL.copy(variants = listOf(variant(id = 1, price = 185_000, unit = VariantUnit.Dozen), variant(2))))
            val vm = editForm()

            vm.onEvent(ProductFormEvent.EditVariant(1))

            val sheet = vm.state.variantSheet!!
            assertEquals(1L, sheet.variantId)
            assertEquals("Ukuran 1", sheet.name)
            assertEquals(VariantUnit.Dozen, sheet.unit)
            assertEquals("185000", sheet.price)
            assertEquals("1", sheet.minQty)
            assertTrue(sheet.isActive)
        }

    @Test
    fun `changing the price puts the variant's details without any stock`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.typeInSheet(VariantField.Price, "189.000")

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            // VariantChanges has no stock field at all; the repository test checks the body.
            val (id, changes) = products.updateVariantCalls.single()
            assertEquals(1L, id)
            assertEquals(189_000L, changes.price)
            assertEquals("Ukuran 1", changes.name)
            assertEquals(BigDecimal("1"), changes.minQty)
            assertTrue(changes.isActive)
            assertEquals(
                189_000L,
                vm.state.variants
                    .first { it.id == 1L }
                    .price,
            )
            assertEquals(UiText.Resource(R.string.product_form_variant_saved, listOf("Ukuran 1")), vm.state.variantMessage)
        }

    @Test
    fun `switching a variant off puts is_active false and the row shows it`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(2))
            vm.onEvent(ProductFormEvent.VariantSheetActiveChanged(false))

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertFalse(
                products.updateVariantCalls
                    .single()
                    .second.isActive,
            )
            assertFalse(
                vm.state.variants
                    .first { it.id == 2L }
                    .isActive,
            )
        }

    @Test
    fun `a variant being saved shows its own indicator until the server answers`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            products.variantGates[1] = gate
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))

            vm.onEvent(ProductFormEvent.SaveVariant)
            runCurrent()

            assertNull(vm.state.variantSheet)
            assertEquals(mapOf(1L to VariantBusy.Saving), vm.state.variantBusy)
            gate.complete(Unit)
            advanceUntilIdle()
            assertEquals(emptyMap<Long, VariantBusy>(), vm.state.variantBusy)
        }

    @Test
    fun `a variant being saved cannot be opened again until it is done`() =
        runTest(main.dispatcher) {
            products.variantGates[1] = CompletableDeferred()
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.onEvent(ProductFormEvent.SaveVariant)
            runCurrent()

            vm.onEvent(ProductFormEvent.EditVariant(1))

            assertNull(vm.state.variantSheet)
        }

    // --- Deleting ---------------------------------------------------------

    @Test
    fun `deleting asks first, then deletes the variant and drops its row`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(2))

            vm.onEvent(ProductFormEvent.DeleteVariant)

            assertNull(vm.state.variantSheet)
            assertEquals(2L, vm.state.variantToDelete?.id)
            assertTrue(products.deleteVariantCalls.isEmpty())

            vm.onEvent(ProductFormEvent.ConfirmDeleteVariant)
            advanceUntilIdle()

            assertEquals(listOf(2L), products.deleteVariantCalls)
            assertNull(vm.state.variantToDelete)
            assertEquals(listOf(1L), vm.state.variants.map { it.id })
            assertEquals(UiText.Resource(R.string.product_form_variant_deleted, listOf("Ukuran 2")), vm.state.variantMessage)
        }

    @Test
    fun `cancelling the delete keeps the variant`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(2))
            vm.onEvent(ProductFormEvent.DeleteVariant)

            vm.onEvent(ProductFormEvent.DismissDeleteVariant)
            advanceUntilIdle()

            assertNull(vm.state.variantToDelete)
            assertTrue(products.deleteVariantCalls.isEmpty())
            assertEquals(2, vm.state.variants.size)
        }

    @Test
    fun `the last variant cannot be deleted and no DELETE is sent`() =
        runTest(main.dispatcher) {
            products.getResult = ApiResult.Success(DETAIL.copy(variants = listOf(variant(1))))
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))

            assertFalse(vm.state.canDeleteVariant)
            vm.onEvent(ProductFormEvent.DeleteVariant)
            vm.onEvent(ProductFormEvent.ConfirmDeleteVariant)
            advanceUntilIdle()

            assertNull(vm.state.variantToDelete)
            assertTrue(products.deleteVariantCalls.isEmpty())
        }

    @Test
    fun `the last variant refused by the server shows its message and reloads the list`() =
        runTest(main.dispatcher) {
            // The other variant was deleted from another device just before.
            val vm = editForm()
            val message = "Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual."
            products.deleteVariantResult = ApiResult.Invalid(mapOf("variant" to listOf(message)), message)
            products.getResult = ApiResult.Success(DETAIL.copy(variants = listOf(variant(1))))
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.onEvent(ProductFormEvent.DeleteVariant)

            vm.onEvent(ProductFormEvent.ConfirmDeleteVariant)
            advanceUntilIdle()

            assertEquals(UiText.Raw(message), vm.state.variantNotice)
            assertEquals(2, products.getCalls.size)
            assertEquals(listOf(1L), vm.state.variants.map { it.id })
            assertFalse(vm.state.canDeleteVariant)
            assertEquals(emptyMap<Long, VariantBusy>(), vm.state.variantBusy)
        }

    // --- Gone or refused --------------------------------------------------

    @Test
    fun `a variant deleted from another device reloads the list when saved`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            products.updateVariantResult = ApiResult.NotFound
            products.getResult = ApiResult.Success(DETAIL.copy(variants = listOf(variant(1))))
            vm.onEvent(ProductFormEvent.EditVariant(2))
            vm.typeInSheet(VariantField.Price, "250000")

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertEquals(2, products.getCalls.size)
            assertEquals(listOf(1L), vm.state.variants.map { it.id })
            assertEquals(UiText.Resource(R.string.product_form_variant_gone, listOf("Ukuran 2")), vm.state.variantNotice)
        }

    @Test
    fun `reloading the variants keeps what was typed in the form`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.TextChanged(ProductTextField.Name, "Kepiting Bakau Jumbo"))
            products.updateVariantResult = ApiResult.NotFound
            vm.onEvent(ProductFormEvent.EditVariant(2))

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertEquals("Kepiting Bakau Jumbo", vm.state.name)
        }

    @Test
    fun `a field refused by the server opens the sheet again with what was typed`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            products.addVariantResult =
                ApiResult.Invalid(mapOf("name" to listOf("Nama ukuran sudah dipakai.")), "Nama ukuran sudah dipakai.")
            vm.fillNewVariant()

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            val sheet = vm.state.variantSheet!!
            assertEquals("1 kg", sheet.name)
            assertEquals("10", sheet.initialStock)
            assertEquals(UiText.Raw("Nama ukuran sudah dipakai."), sheet.errors[VariantField.Name])
            assertEquals(2, vm.state.variants.size)
        }

    @Test
    fun `no connection keeps the variant as it was and says so`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            products.updateVariantResult = ApiResult.NetworkError
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.typeInSheet(VariantField.Price, "999000")

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertEquals(
                185_000L,
                vm.state.variants
                    .first { it.id == 1L }
                    .price,
            )
            assertEquals(UiText.Resource(R.string.error_network), vm.state.variantNotice)
            assertEquals(emptyMap<Long, VariantBusy>(), vm.state.variantBusy)
        }

    // --- Refused on the device --------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("wrongVariants")
    fun `a wrong variant field is refused on the device`(
        case: String,
        field: VariantField,
        value: String,
    ) = runTest(main.dispatcher) {
        val vm = editForm()
        vm.fillNewVariant()
        vm.typeInSheet(field, value)

        vm.onEvent(ProductFormEvent.SaveVariant)
        advanceUntilIdle()

        assertEquals(
            setOf(field),
            vm.state.variantSheet!!
                .errors.keys,
            case,
        )
        assertTrue(products.addVariantCalls.isEmpty(), case)
    }

    @Test
    fun `a wrong field is refused when changing a variant too`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.typeInSheet(VariantField.Price, "0")

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertTrue(VariantField.Price in vm.state.variantSheet!!.errors)
            assertTrue(products.updateVariantCalls.isEmpty())
        }

    @Test
    fun `typing again clears the error of that field`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.fillNewVariant()
            vm.typeInSheet(VariantField.Name, "")
            vm.onEvent(ProductFormEvent.SaveVariant)

            vm.typeInSheet(VariantField.Name, "1 kg")

            assertTrue(
                vm.state.variantSheet!!
                    .errors
                    .isEmpty(),
            )
        }

    // --- Edge cases -------------------------------------------------------

    @Test
    fun `the twentieth variant turns the add button off`() =
        runTest(main.dispatcher) {
            products.getResult = ApiResult.Success(DETAIL.copy(variants = (1L..19L).map { variant(id = it) }))
            val vm = editForm()
            assertTrue(vm.state.canAddVariant)
            vm.fillNewVariant()

            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            assertEquals(20, vm.state.variants.size)
            assertFalse(vm.state.canAddVariant)
            vm.onEvent(ProductFormEvent.AddVariant)
            assertNull(vm.state.variantSheet)
        }

    @Test
    fun `two variants can be saved and deleted at the same time, each with its own indicator`() =
        runTest(main.dispatcher) {
            products.getResult = ApiResult.Success(DETAIL.copy(variants = listOf(variant(1), variant(2), variant(3))))
            val saving = CompletableDeferred<Unit>()
            val deleting = CompletableDeferred<Unit>()
            products.variantGates[1] = saving
            products.variantGates[2] = deleting
            val vm = editForm()

            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.typeInSheet(VariantField.Price, "189000")
            vm.onEvent(ProductFormEvent.SaveVariant)
            vm.onEvent(ProductFormEvent.EditVariant(2))
            vm.onEvent(ProductFormEvent.DeleteVariant)
            vm.onEvent(ProductFormEvent.ConfirmDeleteVariant)
            runCurrent()

            assertEquals(mapOf(1L to VariantBusy.Saving, 2L to VariantBusy.Deleting), vm.state.variantBusy)

            deleting.complete(Unit)
            advanceUntilIdle()
            assertEquals(mapOf(1L to VariantBusy.Saving), vm.state.variantBusy)
            assertEquals(listOf(1L, 3L), vm.state.variants.map { it.id })

            saving.complete(Unit)
            advanceUntilIdle()
            assertEquals(emptyMap<Long, VariantBusy>(), vm.state.variantBusy)
            assertEquals(
                189_000L,
                vm.state.variants
                    .first { it.id == 1L }
                    .price,
            )
        }

    @Test
    fun `a variant being added shows on the add button`() =
        runTest(main.dispatcher) {
            products.variantGates[NEW_VARIANT] = CompletableDeferred()
            val vm = editForm()
            vm.fillNewVariant()

            vm.onEvent(ProductFormEvent.SaveVariant)
            runCurrent()

            assertTrue(vm.state.isAddingVariant)
            assertFalse(vm.state.canAddVariant)
        }

    @Test
    fun `the form's Save sends no variants, and saved variants are not changes to throw away`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.typeInSheet(VariantField.Price, "189000")
            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Back)

            assertFalse(vm.state.showDiscardDialog)
            assertEquals(ProductFormResult.Left, vm.state.result)
            assertTrue(products.updateCalls.isEmpty())
        }

    @Test
    fun `closing the sheet sends nothing`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.fillNewVariant()

            vm.onEvent(ProductFormEvent.DismissVariantSheet)
            advanceUntilIdle()

            assertNull(vm.state.variantSheet)
            assertTrue(products.addVariantCalls.isEmpty())
        }

    @Test
    fun `the message after a save goes once it was shown`() =
        runTest(main.dispatcher) {
            val vm = editForm()
            vm.onEvent(ProductFormEvent.EditVariant(1))
            vm.onEvent(ProductFormEvent.SaveVariant)
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.VariantMessageShown)

            assertNull(vm.state.variantMessage)
        }

    companion object {
        @JvmStatic
        fun wrongVariants(): List<Arguments> =
            listOf(
                arguments("empty name", VariantField.Name, ""),
                arguments("name of 101 characters", VariantField.Name, "a".repeat(101)),
                arguments("price 0", VariantField.Price, "0"),
                arguments("price 100.000.001", VariantField.Price, "100.000.001"),
                arguments("minimum -1", VariantField.MinQty, "-1"),
                arguments("minimum 0", VariantField.MinQty, "0"),
                arguments("opening stock 1,2345", VariantField.InitialStock, "1,2345"),
                arguments("opening stock -1", VariantField.InitialStock, "-1"),
            )
    }
}
