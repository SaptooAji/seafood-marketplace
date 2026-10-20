package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit

/** The form's free-text fields. */
enum class ProductTextField {
    Name,
    Description,
    SeoDescription,
    DailyQuota,
    LeadTimeDays,
    CutoffTime,
    ShelfLifeDays,
    StorageInstructions,
    VariantName,
    VariantPrice,
    VariantMinQty,
    VariantStock,
}

/** A place on the form an error can belong to. */
sealed interface FormField {
    data class Text(
        val field: ProductTextField,
    ) : FormField

    data object Category : FormField

    data object Condition : FormField

    data class Attribute(
        val id: Long,
    ) : FormField
}

/** How the form was left; the screen then goes back to the list. */
enum class ProductFormResult { Saved, Deleted, Left }

/**
 * The product form (board VD-Produk-Form). Numbers and times are kept as
 * typed; they are checked and converted only when saving.
 */
data class ProductFormUiState(
    val isEdit: Boolean = false,
    val isLoading: Boolean = true,
    /** Why the categories or the product could not load; offers to try again. */
    val loadError: UiText? = null,
    val notFound: Boolean = false,
    val categories: List<Category> = emptyList(),
    val categoryId: Long? = null,
    /** The names from the root to the picked category, such as Kepiting, Bakau. */
    val categoryPath: List<String> = emptyList(),
    /** The picked category's effective attributes, in the server's order. */
    val attributes: List<CategoryAttribute> = emptyList(),
    val attributeValues: Map<Long, String> = emptyMap(),
    val name: String = "",
    val description: String = "",
    val seoDescription: String = "",
    val condition: ProductCondition? = null,
    val salesMode: SalesMode = SalesMode.ReadyStock,
    val dailyQuota: String = "",
    val leadTimeDays: String = "",
    /** Shown as `15.00`, the Indonesian way; sent as `15:00`. */
    val cutoffTime: String = "",
    val shelfLifeDays: String = "",
    val storageInstructions: String = "",
    val isActive: Boolean = true,
    // The first variant, asked when adding only; #239 brings the full editor.
    val variantName: String = "",
    val variantUnit: VariantUnit = VariantUnit.Kg,
    val variantPrice: String = "",
    val variantMinQty: String = "1",
    val variantStock: String = "",
    /** The product's variants, shown read-only while editing. */
    val variants: List<ProductVariant> = emptyList(),
    val isBlocked: Boolean = false,
    val blockReason: String? = null,
    val errors: Map<FormField, UiText> = emptyMap(),
    /** An error that belongs to no field: no connection, a server error, or a product that is gone. */
    val generalError: UiText? = null,
    /** The first field with an error; [scrollRequest] grows each time the screen should scroll to it. */
    val scrollTarget: FormField? = null,
    val scrollRequest: Int = 0,
    val isSaving: Boolean = false,
    val isDeleting: Boolean = false,
    val showDiscardDialog: Boolean = false,
    val showDeleteDialog: Boolean = false,
    val result: ProductFormResult? = null,
) {
    val isBusy: Boolean get() = isSaving || isDeleting

    fun text(field: ProductTextField): String =
        when (field) {
            ProductTextField.Name -> name
            ProductTextField.Description -> description
            ProductTextField.SeoDescription -> seoDescription
            ProductTextField.DailyQuota -> dailyQuota
            ProductTextField.LeadTimeDays -> leadTimeDays
            ProductTextField.CutoffTime -> cutoffTime
            ProductTextField.ShelfLifeDays -> shelfLifeDays
            ProductTextField.StorageInstructions -> storageInstructions
            ProductTextField.VariantName -> variantName
            ProductTextField.VariantPrice -> variantPrice
            ProductTextField.VariantMinQty -> variantMinQty
            ProductTextField.VariantStock -> variantStock
        }

    /** [value] typed into [field], whose error clears. */
    fun withText(
        field: ProductTextField,
        value: String,
    ): ProductFormUiState {
        val changed =
            when (field) {
                ProductTextField.Name -> copy(name = value)
                ProductTextField.Description -> copy(description = value)
                ProductTextField.SeoDescription -> copy(seoDescription = value)
                ProductTextField.DailyQuota -> copy(dailyQuota = value)
                ProductTextField.LeadTimeDays -> copy(leadTimeDays = value)
                ProductTextField.CutoffTime -> copy(cutoffTime = value)
                ProductTextField.ShelfLifeDays -> copy(shelfLifeDays = value)
                ProductTextField.StorageInstructions -> copy(storageInstructions = value)
                ProductTextField.VariantName -> copy(variantName = value)
                ProductTextField.VariantPrice -> copy(variantPrice = value)
                ProductTextField.VariantMinQty -> copy(variantMinQty = value)
                ProductTextField.VariantStock -> copy(variantStock = value)
            }
        return changed.copy(errors = errors - FormField.Text(field))
    }

    /**
     * What the seller can change, to tell whether anything was. Empty
     * attribute values count as missing, so a field emptied and typed back
     * is no change.
     */
    fun values(): List<Any?> =
        listOf(
            categoryId,
            attributeValues.filterValues { it.isNotBlank() },
            condition,
            salesMode,
            isActive,
            variantUnit,
        ) + ProductTextField.entries.map { text(it) }
}

sealed interface ProductFormEvent {
    data class TextChanged(
        val field: ProductTextField,
        val value: String,
    ) : ProductFormEvent

    data class AttributeChanged(
        val id: Long,
        val value: String,
    ) : ProductFormEvent

    /** A category was tapped; only a leaf is taken. */
    data class CategoryPicked(
        val id: Long,
    ) : ProductFormEvent

    data class ConditionPicked(
        val condition: ProductCondition,
    ) : ProductFormEvent

    data class SalesModePicked(
        val mode: SalesMode,
    ) : ProductFormEvent

    data class ActiveChanged(
        val active: Boolean,
    ) : ProductFormEvent

    data class VariantUnitPicked(
        val unit: VariantUnit,
    ) : ProductFormEvent

    data object Save : ProductFormEvent

    /** Back, from the top bar or the system. */
    data object Back : ProductFormEvent

    data object ConfirmDiscard : ProductFormEvent

    data object DismissDiscard : ProductFormEvent

    data object Delete : ProductFormEvent

    data object ConfirmDelete : ProductFormEvent

    data object DismissDelete : ProductFormEvent

    /** Load the categories or the product again after a failure. */
    data object Retry : ProductFormEvent
}
