package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
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
import org.junit.jupiter.params.provider.Arguments.arguments
import org.junit.jupiter.params.provider.MethodSource
import java.math.BigDecimal

@OptIn(ExperimentalCoroutinesApi::class)
class ProductFormViewModelTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val products = FakeProductRepository()
    private val categories = FakeCategoryRepository()

    private fun create(handle: SavedStateHandle = SavedStateHandle()) = ProductFormViewModel(products, categories, handle)

    private fun edit(
        id: Long = 7,
        handle: SavedStateHandle = SavedStateHandle(mapOf("productId" to id)),
    ) = ProductFormViewModel(products, categories, handle)

    private fun ProductFormViewModel.type(
        field: ProductTextField,
        value: String,
    ) = onEvent(ProductFormEvent.TextChanged(field, value))

    /** A complete ready-stock Kepiting Bakau with one variant. */
    private fun ProductFormViewModel.fill(
        withCategory: Boolean = true,
        withCondition: Boolean = true,
    ) {
        if (withCategory) {
            onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.BAKAU.id))
            onEvent(ProductFormEvent.AttributeChanged(FakeCategoryRepository.WEIGHT.id, "350"))
            onEvent(ProductFormEvent.AttributeChanged(FakeCategoryRepository.GRADE.id, "Super"))
        }
        type(ProductTextField.Name, "Kepiting Bakau Super")
        type(ProductTextField.Description, "Kepiting bakau jantan dari tambak Muara Angke.")
        if (withCondition) onEvent(ProductFormEvent.ConditionPicked(ProductCondition.Fresh))
        type(ProductTextField.ShelfLifeDays, "2")
        type(ProductTextField.VariantName, "300–400 g per ekor")
        onEvent(ProductFormEvent.VariantUnitPicked(VariantUnit.Kg))
        type(ProductTextField.VariantPrice, "185000")
        type(ProductTextField.VariantMinQty, "1")
        type(ProductTextField.VariantStock, "12")
    }

    // --- Adding ---------------------------------------------------------

    @Test
    fun `a ready-stock product is sent with its attributes and one variant, and no pre-order fields`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            // Filled in, then switched back to ready stock: these are not sent.
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            vm.type(ProductTextField.DailyQuota, "25")
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.ReadyStock))

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            val (draft, variant) = products.createCalls.single()
            assertEquals(2L, draft.categoryId)
            assertEquals(ProductCondition.Fresh, draft.condition)
            assertEquals(SalesMode.ReadyStock, draft.salesMode)
            assertEquals(mapOf(10L to "350", 11L to "Super"), draft.attributes)
            assertNull(draft.dailyQuota)
            assertNull(draft.leadTimeDays)
            assertNull(draft.cutoffTime)
            assertEquals(2, draft.shelfLifeDays)
            assertTrue(draft.isActive)
            assertEquals("300–400 g per ekor", variant.name)
            assertEquals(VariantUnit.Kg, variant.unit)
            assertEquals(185_000L, variant.price)
            assertEquals(BigDecimal("1"), variant.minQty)
            assertEquals(BigDecimal("12"), variant.initialStock)
            assertEquals(ProductFormResult.Saved, vm.uiState.value.result)
        }

    @Test
    fun `a pre-order product sends its quota, lead time, and cutoff`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            vm.type(ProductTextField.DailyQuota, "25")
            vm.type(ProductTextField.LeadTimeDays, "1")
            vm.type(ProductTextField.CutoffTime, "15.00")

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            val draft = products.createCalls.single().first
            assertEquals(SalesMode.PreOrder, draft.salesMode)
            assertEquals(BigDecimal("25"), draft.dailyQuota)
            assertEquals(1, draft.leadTimeDays)
            assertEquals("15:00", draft.cutoffTime)
        }

    @Test
    fun `a quota typed with a comma is sent with a dot`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            vm.type(ProductTextField.DailyQuota, "12,5")

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals("12.5", products.createCalls.single().first.dailyQuota!!.toPlainString())
        }

    @Test
    fun `a description with markup is sent exactly as typed`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            vm.type(ProductTextField.Description, "<script>alert(1)</script> Segar")

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals("<script>alert(1)</script> Segar", products.createCalls.single().first.description)
        }

    @Test
    fun `saving twice quickly sends one request`() =
        runTest(main.dispatcher) {
            products.saveGate = CompletableDeferred()
            val vm = create()
            advanceUntilIdle()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            runCurrent()
            assertTrue(vm.uiState.value.isSaving)
            vm.onEvent(ProductFormEvent.Save)
            runCurrent()

            products.saveGate!!.complete(Unit)
            advanceUntilIdle()
            assertEquals(1, products.createCalls.size)
        }

    // --- Editing ----------------------------------------------------------

    @Test
    fun `editing fills the form from the product`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()

            val state = vm.uiState.value
            assertEquals(listOf(7L), products.getCalls)
            assertTrue(state.isEdit)
            assertEquals("Kepiting Bakau Super", state.name)
            assertEquals(2L, state.categoryId)
            assertEquals(listOf("Kepiting", "Bakau"), state.categoryPath)
            assertEquals(listOf(10L, 11L), state.attributes.map { it.id })
            assertEquals(mapOf(10L to "350", 11L to "Super"), state.attributeValues)
            assertEquals(ProductCondition.Live, state.condition)
            assertEquals(SalesMode.PreOrder, state.salesMode)
            assertEquals("25", state.dailyQuota)
            assertEquals("15.00", state.cutoffTime)
            assertEquals(2, state.variants.size)
        }

    @Test
    fun `saving an edit puts the product without variants`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()
            vm.type(ProductTextField.Name, "Kepiting Bakau Jumbo")

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            val (id, draft) = products.updateCalls.single()
            assertEquals(7L, id)
            assertEquals("Kepiting Bakau Jumbo", draft.name)
            assertEquals("15:00", draft.cutoffTime)
            assertTrue(products.createCalls.isEmpty())
            assertEquals(ProductFormResult.Saved, vm.uiState.value.result)
        }

    @Test
    fun `changing the category drops the attributes it does not have and keeps the shared ones`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.SOKA.id))

            assertEquals(3L, vm.uiState.value.categoryId)
            assertEquals(mapOf(10L to "350"), vm.uiState.value.attributeValues)
            assertEquals(listOf(10L), vm.uiState.value.attributes.map { it.id })
        }

    @Test
    fun `a parent category cannot be picked`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.KEPITING.id))

            assertNull(vm.uiState.value.categoryId)
        }

    @Test
    fun `a product blocked by the admin shows the reason and can still be edited`() =
        runTest(main.dispatcher) {
            products.getResult = ApiResult.Success(FakeProductRepository.DETAIL.copy(isBlocked = true, blockReason = "Foto tidak sesuai produk."))
            val vm = edit()
            advanceUntilIdle()

            assertTrue(vm.uiState.value.isBlocked)
            assertEquals("Foto tidak sesuai produk.", vm.uiState.value.blockReason)

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()
            assertEquals(1, products.updateCalls.size)
        }

    // --- Deleting ---------------------------------------------------------

    @Test
    fun `deleting asks first, then deletes and leaves`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Delete)
            assertTrue(vm.uiState.value.showDeleteDialog)
            assertTrue(products.deleteCalls.isEmpty())

            vm.onEvent(ProductFormEvent.ConfirmDelete)
            advanceUntilIdle()

            assertEquals(listOf(7L), products.deleteCalls)
            assertEquals(ProductFormResult.Deleted, vm.uiState.value.result)
        }

    @Test
    fun `cancelling the delete keeps the product`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Delete)
            vm.onEvent(ProductFormEvent.DismissDelete)

            assertFalse(vm.uiState.value.showDeleteDialog)
            assertTrue(products.deleteCalls.isEmpty())
        }

    // --- Refused before sending ------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("refusals")
    fun `a wrong field is refused on the device`(
        case: String,
        change: (ProductFormViewModel) -> Unit,
        field: FormField,
        message: Int,
    ) = runTest(main.dispatcher) {
        val vm = create()
        advanceUntilIdle()
        vm.fill()
        change(vm)

        vm.onEvent(ProductFormEvent.Save)
        advanceUntilIdle()

        assertTrue(products.createCalls.isEmpty(), case)
        assertEquals(UiText.Resource(message), vm.uiState.value.errors[field], case)
    }

    @Test
    fun `a product without a category or a condition is refused at both`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill(withCategory = false, withCondition = false)

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(products.createCalls.isEmpty())
            assertEquals(UiText.Resource(R.string.product_form_error_category_required), vm.uiState.value.errors[FormField.Category])
            assertEquals(UiText.Resource(R.string.product_form_error_condition_required), vm.uiState.value.errors[FormField.Condition])
        }

    @Test
    fun `the first wrong field in the form is the one to scroll to`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            vm.type(ProductTextField.VariantPrice, "0")
            vm.type(ProductTextField.Name, "")
            val before = vm.uiState.value.scrollRequest

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(FormField.Text(ProductTextField.Name), vm.uiState.value.scrollTarget)
            assertTrue(vm.uiState.value.scrollRequest > before)
        }

    @Test
    fun `typing again clears the error of that field`() =
        runTest(main.dispatcher) {
            val vm = create()
            advanceUntilIdle()
            vm.fill()
            vm.type(ProductTextField.Name, "")
            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            vm.type(ProductTextField.Name, "K")

            assertNull(vm.uiState.value.errors[FormField.Text(ProductTextField.Name)])
        }

    // --- Refused by the server -------------------------------------------

    @Test
    fun `the server's category message shows at the category picker`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.Invalid(mapOf("category_id" to listOf("Pilih subkategori yang paling spesifik.")), null)
            val vm = create()
            advanceUntilIdle()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Pilih subkategori yang paling spesifik."), vm.uiState.value.errors[FormField.Category])
            assertNull(vm.uiState.value.result)
        }

    @Test
    fun `the server's attribute message shows at that attribute`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.Invalid(mapOf("attributes.10" to listOf("Berat per ekor harus berupa angka.")), null)
            val vm = create()
            advanceUntilIdle()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Raw("Berat per ekor harus berupa angka."), vm.uiState.value.errors[FormField.Attribute(10)])
        }

    @Test
    fun `a refused first variant price shows in the variant row`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.Invalid(mapOf("variants.0.price" to listOf("The variants.0.price field must be at least 1.")), null)
            val vm = create()
            advanceUntilIdle()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(
                UiText.Resource(R.string.product_form_error_price),
                vm.uiState.value.errors[FormField.Text(ProductTextField.VariantPrice)],
            )
        }

    @Test
    fun `a product of another store is not found`() =
        runTest(main.dispatcher) {
            products.getResult = ApiResult.NotFound
            val vm = edit(id = 99)
            advanceUntilIdle()

            assertTrue(vm.uiState.value.notFound)
        }

    @Test
    fun `saving a product that is gone changes nothing on the form`() =
        runTest(main.dispatcher) {
            products.updateResult = ApiResult.NotFound
            val vm = edit()
            advanceUntilIdle()
            vm.type(ProductTextField.Name, "Kepiting Bakau Jumbo")

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals("Kepiting Bakau Jumbo", vm.uiState.value.name)
            assertEquals(UiText.Resource(R.string.product_form_not_found), vm.uiState.value.generalError)
            assertNull(vm.uiState.value.result)
        }

    @Test
    fun `no connection keeps the form and offers to try again`() =
        runTest(main.dispatcher) {
            products.createResult = ApiResult.NetworkError
            val vm = create()
            advanceUntilIdle()
            vm.fill()

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.generalError)
            assertFalse(vm.uiState.value.isSaving)
        }

    @Test
    fun `categories that fail to load can be loaded again`() =
        runTest(main.dispatcher) {
            categories.result = ApiResult.NetworkError
            val vm = create()
            advanceUntilIdle()
            assertEquals(UiText.Resource(R.string.error_network), vm.uiState.value.loadError)

            categories.result = ApiResult.Success(FakeCategoryRepository.TREE)
            vm.onEvent(ProductFormEvent.Retry)
            advanceUntilIdle()

            assertNull(vm.uiState.value.loadError)
            assertEquals(4, vm.uiState.value.categories.size)
        }

    // --- Leaving ------------------------------------------------------------

    @Test
    fun `going back without changes leaves at once`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()

            vm.onEvent(ProductFormEvent.Back)

            assertFalse(vm.uiState.value.showDiscardDialog)
            assertEquals(ProductFormResult.Left, vm.uiState.value.result)
        }

    @Test
    fun `going back with changes asks before throwing them away`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()
            vm.type(ProductTextField.Name, "Kepiting Bakau Jumbo")

            vm.onEvent(ProductFormEvent.Back)
            assertTrue(vm.uiState.value.showDiscardDialog)
            assertNull(vm.uiState.value.result)

            vm.onEvent(ProductFormEvent.DismissDiscard)
            assertFalse(vm.uiState.value.showDiscardDialog)

            vm.onEvent(ProductFormEvent.Back)
            vm.onEvent(ProductFormEvent.ConfirmDiscard)
            assertEquals(ProductFormResult.Left, vm.uiState.value.result)
        }

    @Test
    fun `an emptied field typed back as it was is not a change`() =
        runTest(main.dispatcher) {
            val vm = edit()
            advanceUntilIdle()
            vm.type(ProductTextField.Name, "")
            vm.type(ProductTextField.Name, "Kepiting Bakau Super")

            vm.onEvent(ProductFormEvent.Back)

            assertEquals(ProductFormResult.Left, vm.uiState.value.result)
        }

    // --- Edge cases -------------------------------------------------------

    @Test
    fun `a recreated view model keeps the fields and the category`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            create(handle).apply {
                advanceUntilIdle()
                fill()
            }

            val recreated = create(handle)
            advanceUntilIdle()

            val state = recreated.uiState.value
            assertEquals("Kepiting Bakau Super", state.name)
            assertEquals(2L, state.categoryId)
            assertEquals(mapOf(10L to "350", 11L to "Super"), state.attributeValues)
            assertEquals(ProductCondition.Fresh, state.condition)
            assertEquals("185000", state.variantPrice)
        }

    @Test
    fun `a recreated edit form does not load the product over what was typed`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle(mapOf("productId" to 7L))
            edit(handle = handle).apply {
                advanceUntilIdle()
                type(ProductTextField.Name, "Kepiting Bakau Jumbo")
            }

            val recreated = edit(handle = handle)
            advanceUntilIdle()

            assertEquals("Kepiting Bakau Jumbo", recreated.uiState.value.name)
        }

    companion object {
        private fun refused(
            case: String,
            field: FormField,
            message: Int,
            change: (ProductFormViewModel) -> Unit,
        ) = arguments(case, change, field, message)

        private fun ProductFormViewModel.set(
            field: ProductTextField,
            value: String,
        ) = onEvent(ProductFormEvent.TextChanged(field, value))

        private fun preOrder(vm: ProductFormViewModel) = vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))

        @JvmStatic
        fun refusals(): List<Arguments> =
            listOf(
                refused("empty name", FormField.Text(ProductTextField.Name), R.string.product_form_error_name_required) {
                    it.set(ProductTextField.Name, " ")
                },
                refused("empty description", FormField.Text(ProductTextField.Description), R.string.product_form_error_description_required) {
                    it.set(ProductTextField.Description, "")
                },
                refused("required attribute empty", FormField.Attribute(10), R.string.product_form_error_attribute_required) {
                    it.onEvent(ProductFormEvent.AttributeChanged(10, " "))
                },
                refused("number attribute not a number", FormField.Attribute(10), R.string.product_form_error_attribute_number) {
                    it.onEvent(ProductFormEvent.AttributeChanged(10, "tiga ratus"))
                },
                refused("pre order without quota", FormField.Text(ProductTextField.DailyQuota), R.string.product_form_error_quota_required) {
                    preOrder(it)
                },
                refused("cutoff 25:00", FormField.Text(ProductTextField.CutoffTime), R.string.product_form_error_cutoff) {
                    preOrder(it)
                    it.set(ProductTextField.DailyQuota, "25")
                    it.set(ProductTextField.CutoffTime, "25:00")
                },
                refused("price 0", FormField.Text(ProductTextField.VariantPrice), R.string.product_form_error_price) {
                    it.set(ProductTextField.VariantPrice, "0")
                },
                refused("price over 100 million", FormField.Text(ProductTextField.VariantPrice), R.string.product_form_error_price) {
                    it.set(ProductTextField.VariantPrice, "100000001")
                },
                refused("name over 255", FormField.Text(ProductTextField.Name), R.string.product_form_error_name_too_long) {
                    it.set(ProductTextField.Name, "a".repeat(256))
                },
                refused("stock with four decimals", FormField.Text(ProductTextField.VariantStock), R.string.product_form_error_decimal) {
                    it.set(ProductTextField.VariantStock, "1,2345")
                },
            )
    }
}
