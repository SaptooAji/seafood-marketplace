package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.DETAIL
import io.github.saptooaji.seafood.seller.feature.product.FakeProductRepository.Companion.SUGGESTION
import io.github.saptooaji.seafood.seller.feature.product.domain.DescriptionSuggestion
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
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
 * The AI description of the product form (#240, boards VD-Saran-AI): only a
 * suggestion, which changes the form when the seller uses it.
 */
@OptIn(ExperimentalCoroutinesApi::class)
class ProductFormSuggestionTest {
    @JvmField
    @RegisterExtension
    val main = MainDispatcherExtension()

    private val products = FakeProductRepository()
    private val categories = FakeCategoryRepository()

    private val ProductFormViewModel.state get() = uiState.value

    private fun TestScope.addForm(handle: SavedStateHandle = SavedStateHandle()): ProductFormViewModel {
        val vm = ProductFormViewModel(products, categories, handle)
        advanceUntilIdle()
        return vm
    }

    private fun ProductFormViewModel.type(
        field: ProductTextField,
        value: String,
    ) = onEvent(ProductFormEvent.TextChanged(field, value))

    /** What the AI needs: a name, a category, and a condition. Ready stock is the default. */
    private fun ProductFormViewModel.fillRequired() {
        onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.BAKAU.id))
        type(ProductTextField.Name, "Kepiting Bakau Super")
        onEvent(ProductFormEvent.ConditionPicked(ProductCondition.Live))
    }

    private fun TestScope.suggest(vm: ProductFormViewModel) {
        vm.onEvent(ProductFormEvent.SuggestDescription)
        advanceUntilIdle()
    }

    // --- The button ------------------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("missingFields")
    fun `the button waits for a name, a category, and a condition`(
        case: String,
        name: String,
        withCategory: Boolean,
        withCondition: Boolean,
    ) = runTest(main.dispatcher) {
        val vm = addForm()
        if (withCategory) vm.onEvent(ProductFormEvent.CategoryPicked(FakeCategoryRepository.BAKAU.id))
        vm.type(ProductTextField.Name, name)
        if (withCondition) vm.onEvent(ProductFormEvent.ConditionPicked(ProductCondition.Live))

        assertFalse(vm.state.canSuggestDescription, case)

        vm.onEvent(ProductFormEvent.SuggestDescription)
        advanceUntilIdle()
        assertTrue(products.suggestCalls.isEmpty(), case)
        assertNull(vm.state.suggestion, case)
    }

    @Test
    fun `with a name, a category, and a condition the button is on`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()

            assertTrue(vm.state.canSuggestDescription)
        }

    // --- What is sent ----------------------------------------------------

    @Test
    fun `the request carries the form as it is, with the description already written`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()
            vm.onEvent(ProductFormEvent.AttributeChanged(FakeCategoryRepository.WEIGHT.id, "350"))
            vm.onEvent(ProductFormEvent.AttributeChanged(FakeCategoryRepository.GRADE.id, "Super"))
            vm.type(ProductTextField.Description, "  Kepiting bakau jantan.  ")
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            vm.type(ProductTextField.DailyQuota, "12,5")
            vm.type(ProductTextField.LeadTimeDays, "1")
            vm.type(ProductTextField.StorageInstructions, "Simpan di wadah lembap dan sejuk.")
            // The first variant is not part of the request.
            vm.type(ProductTextField.VariantName, "300–400 g per ekor")
            vm.type(ProductTextField.VariantPrice, "185000")

            suggest(vm)

            val draft = products.suggestCalls.single()
            assertEquals("Kepiting Bakau Super", draft.name)
            assertEquals(FakeCategoryRepository.BAKAU.id, draft.categoryId)
            assertEquals(ProductCondition.Live, draft.condition)
            assertEquals(SalesMode.PreOrder, draft.salesMode)
            assertEquals(mapOf(10L to "350", 11L to "Super"), draft.attributes)
            assertEquals("Kepiting bakau jantan.", draft.description)
            assertEquals(BigDecimal("12.5"), draft.dailyQuota)
            assertEquals(1, draft.leadTimeDays)
            assertEquals("Simpan di wadah lembap dan sejuk.", draft.storageInstructions)
        }

    @Test
    fun `numbers that are not numbers yet are left out instead of blocking the request`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            vm.type(ProductTextField.DailyQuota, "dua puluh")
            vm.type(ProductTextField.ShelfLifeDays, "2 hari")

            suggest(vm)

            val draft = products.suggestCalls.single()
            assertNull(draft.dailyQuota)
            assertNull(draft.shelfLifeDays)
        }

    @Test
    fun `an edit form asks with the product as loaded`() =
        runTest(main.dispatcher) {
            val vm = ProductFormViewModel(products, categories, SavedStateHandle(mapOf("productId" to DETAIL.id)))
            advanceUntilIdle()

            assertTrue(vm.state.canSuggestDescription)
            suggest(vm)

            assertEquals(DETAIL.draft.name, products.suggestCalls.single().name)
            assertEquals(DETAIL.draft.description, products.suggestCalls.single().description)
        }

    // --- Waiting ---------------------------------------------------------

    @Test
    fun `the sheet shows it is working until the answer, and a second tap sends nothing`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            products.suggestGate = gate
            val vm = addForm()
            vm.fillRequired()

            vm.onEvent(ProductFormEvent.SuggestDescription)
            runCurrent()
            assertEquals(SuggestionSheet.Loading, vm.state.suggestion)
            assertFalse(vm.state.canSuggestDescription)
            vm.onEvent(ProductFormEvent.SuggestDescription)
            runCurrent()
            assertEquals(1, products.suggestCalls.size)

            gate.complete(Unit)
            advanceUntilIdle()

            assertEquals(SuggestionSheet.Ready(SUGGESTION), vm.state.suggestion)
            // Shown only: the form is unchanged until the seller uses it.
            assertEquals("", vm.state.description)
            assertEquals("", vm.state.seoDescription)
        }

    @Test
    fun `closing the sheet while waiting drops the answer`() =
        runTest(main.dispatcher) {
            val gate = CompletableDeferred<Unit>()
            products.suggestGate = gate
            val vm = addForm()
            vm.fillRequired()
            vm.onEvent(ProductFormEvent.SuggestDescription)
            runCurrent()

            vm.onEvent(ProductFormEvent.DismissSuggestion)
            gate.complete(Unit)
            advanceUntilIdle()

            assertNull(vm.state.suggestion)
            assertEquals("", vm.state.description)
            assertTrue(vm.state.canSuggestDescription)
        }

    // --- Using it --------------------------------------------------------

    @Test
    fun `using it on an empty description fills both fields at once`() =
        runTest(main.dispatcher) {
            val handle = SavedStateHandle()
            val vm = addForm(handle)
            vm.fillRequired()
            suggest(vm)

            vm.onEvent(ProductFormEvent.UseSuggestion)

            assertNull(vm.state.suggestion)
            assertEquals(SUGGESTION.description, vm.state.description)
            assertEquals(SUGGESTION.seoDescription, vm.state.seoDescription)
            // The form changed, so leaving asks first, and a recreated form keeps the text.
            vm.onEvent(ProductFormEvent.Back)
            assertTrue(vm.state.showDiscardDialog)
            val recreated = addForm(handle)
            assertEquals(SUGGESTION.description, recreated.state.description)
        }

    @Test
    fun `using it over a written description asks first, and the second tap replaces it`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()
            vm.type(ProductTextField.Description, "Deskripsi saya sendiri.")
            suggest(vm)

            vm.onEvent(ProductFormEvent.UseSuggestion)
            assertEquals(SuggestionSheet.Ready(SUGGESTION, confirmReplace = true), vm.state.suggestion)
            assertEquals("Deskripsi saya sendiri.", vm.state.description)

            vm.onEvent(ProductFormEvent.UseSuggestion)

            assertNull(vm.state.suggestion)
            assertEquals(SUGGESTION.description, vm.state.description)
            assertEquals(SUGGESTION.seoDescription, vm.state.seoDescription)
        }

    @Test
    fun `cancelling the replace keeps the written description`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()
            vm.type(ProductTextField.Description, "Deskripsi saya sendiri.")
            vm.type(ProductTextField.SeoDescription, "Ringkasan saya.")
            suggest(vm)
            vm.onEvent(ProductFormEvent.UseSuggestion)

            vm.onEvent(ProductFormEvent.DismissSuggestion)

            assertNull(vm.state.suggestion)
            assertEquals("Deskripsi saya sendiri.", vm.state.description)
            assertEquals("Ringkasan saya.", vm.state.seoDescription)
        }

    @Test
    fun `cancelling a suggestion changes nothing`() =
        runTest(main.dispatcher) {
            val vm = addForm()
            vm.fillRequired()
            suggest(vm)

            vm.onEvent(ProductFormEvent.DismissSuggestion)

            assertNull(vm.state.suggestion)
            assertEquals("", vm.state.description)
            assertEquals("", vm.state.seoDescription)
        }

    @Test
    fun `a suggestion with markup is kept as plain text`() =
        runTest(main.dispatcher) {
            products.suggestResult = ApiResult.Success(DescriptionSuggestion("<b>Segar</b> setiap pagi.", "<i>Segar</i>"))
            val vm = addForm()
            vm.fillRequired()
            suggest(vm)

            vm.onEvent(ProductFormEvent.UseSuggestion)

            assertEquals("<b>Segar</b> setiap pagi.", vm.state.description)
            assertEquals("<i>Segar</i>", vm.state.seoDescription)
        }

    @Test
    fun `a summary longer than 160 is still filled in, and refused only on saving`() =
        runTest(main.dispatcher) {
            val long = "Kepiting bakau ".repeat(12).trim()
            products.suggestResult = ApiResult.Success(SUGGESTION.copy(seoDescription = long))
            val vm = addForm()
            vm.fillRequired()
            suggest(vm)

            vm.onEvent(ProductFormEvent.UseSuggestion)
            assertEquals(long, vm.state.seoDescription)
            assertTrue(vm.state.errors.isEmpty())

            vm.onEvent(ProductFormEvent.Save)
            advanceUntilIdle()

            assertTrue(FormField.Text(ProductTextField.SeoDescription) in vm.state.errors)
            assertTrue(products.createCalls.isEmpty())
        }

    // --- Refusals --------------------------------------------------------

    @ParameterizedTest(name = "{0}")
    @MethodSource("failures")
    fun `a refusal is told in the sheet and the form stays as it was`(
        case: String,
        result: ApiResult<DescriptionSuggestion>,
        message: UiText,
        canRetry: Boolean,
    ) = runTest(main.dispatcher) {
        products.suggestResult = result
        val vm = addForm()
        vm.fillRequired()
        vm.type(ProductTextField.Description, "Deskripsi saya sendiri.")

        suggest(vm)

        assertEquals(SuggestionSheet.Failed(message, canRetry), vm.state.suggestion, case)
        assertEquals("Deskripsi saya sendiri.", vm.state.description, case)
        assertEquals("", vm.state.seoDescription, case)
        // Closing it leaves the button, so the seller can ask again later (no automatic retry).
        vm.onEvent(ProductFormEvent.DismissSuggestion)
        assertNull(vm.state.suggestion, case)
        assertTrue(vm.state.canSuggestDescription, case)
        assertEquals(1, products.suggestCalls.size, case)
    }

    @Test
    fun `trying again from a connection error asks once more`() =
        runTest(main.dispatcher) {
            products.suggestResult = ApiResult.NetworkError
            val vm = addForm()
            vm.fillRequired()
            suggest(vm)

            products.suggestResult = ApiResult.Success(SUGGESTION)
            vm.onEvent(ProductFormEvent.SuggestDescription)
            advanceUntilIdle()

            assertEquals(2, products.suggestCalls.size)
            assertEquals(SuggestionSheet.Ready(SUGGESTION), vm.state.suggestion)
        }

    @Test
    fun `a 422 closes the sheet and shows the errors on the form`() =
        runTest(main.dispatcher) {
            products.suggestResult =
                ApiResult.Invalid(mapOf("daily_quota" to listOf("Kuota harian wajib diisi.")), "Kuota harian wajib diisi.")
            val vm = addForm()
            vm.fillRequired()
            vm.onEvent(ProductFormEvent.SalesModePicked(SalesMode.PreOrder))
            val scrolls = vm.state.scrollRequest

            suggest(vm)

            assertNull(vm.state.suggestion)
            assertEquals(UiText.Raw("Kuota harian wajib diisi."), vm.state.errors[FormField.Text(ProductTextField.DailyQuota)])
            assertEquals(FormField.Text(ProductTextField.DailyQuota), vm.state.scrollTarget)
            assertEquals(scrolls + 1, vm.state.scrollRequest)
        }

    @Test
    fun `a 422 on no field of the form is told in the sheet`() =
        runTest(main.dispatcher) {
            products.suggestResult = ApiResult.Invalid(emptyMap(), "Data produk tidak valid.")
            val vm = addForm()
            vm.fillRequired()

            suggest(vm)

            assertEquals(SuggestionSheet.Failed(UiText.Raw("Data produk tidak valid."), canRetry = false), vm.state.suggestion)
        }

    companion object {
        private const val UNAVAILABLE = "Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti atau tulis deskripsi sendiri."

        @JvmStatic
        fun missingFields(): List<Arguments> =
            listOf(
                arguments("an empty form", "", false, false),
                arguments("no name", "", true, true),
                arguments("a name of spaces", "   ", true, true),
                arguments("no category", "Kepiting Bakau Super", false, true),
                arguments("no condition", "Kepiting Bakau Super", true, false),
            )

        @JvmStatic
        fun failures(): List<Arguments> =
            listOf(
                arguments(
                    "429 for a minute",
                    ApiResult.TooManyRequests(60),
                    UiText.Resource(R.string.product_suggestion_too_often, listOf(1L)),
                    false,
                ),
                arguments(
                    "429 for 90 seconds rounds up",
                    ApiResult.TooManyRequests(90),
                    UiText.Resource(R.string.product_suggestion_too_often, listOf(2L)),
                    false,
                ),
                arguments(
                    "429 for an hour is the daily quota",
                    ApiResult.TooManyRequests(3600),
                    UiText.Resource(R.string.product_suggestion_quota_used),
                    false,
                ),
                arguments(
                    "429 without Retry-After",
                    ApiResult.TooManyRequests(null),
                    UiText.Resource(R.string.product_suggestion_wait),
                    false,
                ),
                arguments("503 with the server's message", ApiResult.ServiceUnavailable(UNAVAILABLE), UiText.Raw(UNAVAILABLE), false),
                arguments(
                    "503 without a message",
                    ApiResult.ServiceUnavailable(null),
                    UiText.Resource(R.string.product_suggestion_unavailable),
                    false,
                ),
                arguments("no connection", ApiResult.NetworkError, UiText.Resource(R.string.error_network), true),
                arguments("the server cannot be reached", ApiResult.ServerUnreachable, UiText.Resource(R.string.error_server), true),
                arguments("a server error", ApiResult.ServerError, UiText.Resource(R.string.error_server), true),
            )
    }
}
