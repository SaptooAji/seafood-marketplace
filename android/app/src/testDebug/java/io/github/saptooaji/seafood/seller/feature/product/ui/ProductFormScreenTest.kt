package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.onNodeWithText
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import androidx.test.core.app.ApplicationProvider
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.FakeCategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * The product form as the board VD-Produk-Form draws it, driven by state only.
 * Robolectric's screen is small, so fields below the fold are scrolled to.
 */
@RunWith(RobolectricTestRunner::class)
class ProductFormScreenTest {
    @get:Rule
    val compose = createComposeRule()

    private val context = ApplicationProvider.getApplicationContext<android.app.Application>()
    private val events = mutableListOf<ProductFormEvent>()

    private fun show(state: ProductFormUiState) {
        compose.setContent { SeafoodTheme { ProductFormScreen(uiState = state, onEvent = { events += it }) } }
    }

    private val loaded = ProductFormUiState(isLoading = false, categories = FakeCategoryRepository.TREE)

    private val bakau =
        loaded.copy(
            categoryId = 2,
            categoryPath = listOf("Kepiting", "Bakau"),
            attributes = FakeCategoryRepository.BAKAU.attributes,
        )

    @Test
    fun anAddFormHasNoDeleteAndSendsWhatIsTyped() {
        show(loaded)

        compose.onNodeWithText(context.getString(R.string.product_form_add_title)).assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.NAME).performScrollTo().performTextInput("Kepiting Bakau Super")
        compose.onNodeWithTag(ProductFormTags.DELETE).assertDoesNotExist()
        compose.onNodeWithTag(ProductFormTags.SAVE).performClick()

        assertTrue(events.contains(ProductFormEvent.TextChanged(ProductTextField.Name, "Kepiting Bakau Super")))
        assertEquals(ProductFormEvent.Save, events.last())
    }

    @Test
    fun attributesFollowTheirTypeAndRequiredOnesAreMarked() {
        show(bakau)

        compose.onNodeWithTag(ProductFormTags.attribute(10)).performScrollTo()
        compose.onNodeWithText("Berat per ekor *").assertIsDisplayed()
        compose.onNodeWithText("g").assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.attributeOption(11, "Jantan")).performScrollTo().performClick()

        assertEquals(ProductFormEvent.AttributeChanged(11, "Jantan"), events.last())
    }

    @Test
    fun theCategoryPickerOpensParentsAndPicksOnlyLeaves() {
        show(loaded)

        compose.onNodeWithTag(ProductFormTags.CATEGORY).performScrollTo().performClick()
        compose.onNodeWithTag(ProductFormTags.categoryRow(1)).performClick()
        assertFalse(events.any { it is ProductFormEvent.CategoryPicked })

        compose.onNodeWithTag(ProductFormTags.categoryRow(2)).performClick()

        assertEquals(ProductFormEvent.CategoryPicked(2), events.last())
    }

    @Test
    fun preOrderShowsItsFieldsAndReadyStockHidesThem() {
        show(bakau.copy(salesMode = SalesMode.ReadyStock))
        compose.onNodeWithTag(ProductFormTags.QUOTA).assertDoesNotExist()

        compose.onNodeWithTag(ProductFormTags.salesMode(SalesMode.PreOrder)).performScrollTo().performClick()
        assertEquals(ProductFormEvent.SalesModePicked(SalesMode.PreOrder), events.last())
    }

    @Test
    fun aPreOrderFormHasQuotaLeadTimeAndCutoff() {
        show(bakau.copy(salesMode = SalesMode.PreOrder))

        compose.onNodeWithTag(ProductFormTags.QUOTA).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.LEAD_TIME).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.CUTOFF).performScrollTo().assertIsDisplayed()
    }

    @Test
    fun theConditionChipsSendTheirValue() {
        show(bakau)

        compose.onNodeWithTag(ProductFormTags.condition(ProductCondition.Marinated)).performScrollTo().performClick()

        assertEquals(ProductFormEvent.ConditionPicked(ProductCondition.Marinated), events.last())
    }

    @Test
    fun theSeoSummaryCountsItsCharacters() {
        show(bakau.copy(seoDescription = "a".repeat(68)))

        compose.onNodeWithText("68 / 160").performScrollTo().assertIsDisplayed()
    }

    @Test
    fun aBlockedProductShowsTheReasonAboveTheForm() {
        show(bakau.copy(isEdit = true, isBlocked = true, blockReason = "Foto tidak sesuai produk."))

        compose.onNodeWithText(context.getString(R.string.product_form_blocked, "Foto tidak sesuai produk.")).assertIsDisplayed()
    }

    @Test
    fun aDescriptionWithMarkupIsShownAsText() {
        show(bakau.copy(isEdit = true, description = "<script>alert(1)</script> Segar"))

        compose
            .onNodeWithTag(ProductFormTags.DESCRIPTION)
            .performScrollTo()
            .assertTextContains("<script>alert(1)</script> Segar")
    }

    @Test
    fun anErrorShowsUnderItsField() {
        show(bakau.copy(errors = mapOf(FormField.Category to UiText.Raw("Pilih subkategori yang paling spesifik."))))

        compose.onNodeWithText("Pilih subkategori yang paling spesifik.").performScrollTo().assertIsDisplayed()
    }

    @Test
    fun anEditFormAsksBeforeDeleting() {
        show(bakau.copy(isEdit = true, name = "Kepiting Bakau Super"))
        compose.onNodeWithTag(ProductFormTags.DELETE).performScrollTo().performClick()
        assertEquals(ProductFormEvent.Delete, events.last())
    }

    @Test
    fun theDeleteDialogNamesTheProduct() {
        show(bakau.copy(isEdit = true, name = "Kepiting Bakau Super", showDeleteDialog = true))

        compose.onNodeWithText(context.getString(R.string.product_form_delete_title, "Kepiting Bakau Super")).assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.CONFIRM_DELETE).performClick()

        assertEquals(ProductFormEvent.ConfirmDelete, events.last())
    }

    @Test
    fun theDiscardDialogCanThrowAwayOrKeepEditing() {
        show(bakau.copy(showDiscardDialog = true))

        compose.onNodeWithText(context.getString(R.string.product_form_discard_title)).assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.KEEP_EDITING).performClick()
        compose.onNodeWithTag(ProductFormTags.CONFIRM_DISCARD).performClick()

        assertEquals(listOf(ProductFormEvent.DismissDiscard, ProductFormEvent.ConfirmDiscard), events.takeLast(2))
    }

    @Test
    fun aMissingProductOffersTheWayBack() {
        show(ProductFormUiState(isLoading = false, isEdit = true, notFound = true))

        compose.onNodeWithText(context.getString(R.string.product_form_not_found)).assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.BACK_TO_LIST).performClick()

        assertEquals(ProductFormEvent.Back, events.last())
    }

    @Test
    fun backSendsBack() {
        show(bakau)

        compose.onNodeWithTag(ProductFormTags.BACK).performClick()

        assertEquals(ProductFormEvent.Back, events.last())
    }
}
