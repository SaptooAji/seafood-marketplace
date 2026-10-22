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
    // The first variant, asked when adding only; a saved product edits its variants in [variantSheet].
    val variantName: String = "",
    val variantUnit: VariantUnit = VariantUnit.Kg,
    val variantPrice: String = "",
    val variantMinQty: String = "1",
    val variantStock: String = "",
    /** The product's variants while editing, each saved to the server on its own. */
    val variants: List<ProductVariant> = emptyList(),
    /** The open add or edit sheet of a variant (board VD-Varian). */
    val variantSheet: VariantSheet? = null,
    /** What each variant row is waiting for, by variant id; rows not in it are idle. */
    val variantBusy: Map<Long, VariantBusy> = emptyMap(),
    val isAddingVariant: Boolean = false,
    /** The variant whose delete waits for the seller's confirmation. */
    val variantToDelete: ProductVariant? = null,
    /** Shown in the variant section until the next variant action: a refusal, or a list reloaded. */
    val variantNotice: UiText? = null,
    /** Shown briefly after a variant was saved; the screen sends [ProductFormEvent.VariantMessageShown]. */
    val variantMessage: UiText? = null,
    val isBlocked: Boolean = false,
    val blockReason: String? = null,
    /** The product's photos in order, saved or on their way (#241); the first is the main photo. */
    val photos: List<FormPhoto> = emptyList(),
    /** Shown under the photo row until the next photo action: a stopped queue, a failed reorder or delete. */
    val photoNotice: UiText? = null,
    /** The saved photo whose actions sheet is open. */
    val photoActions: String? = null,
    /** The saved photo whose delete waits for the seller's confirmation. */
    val photoToDelete: String? = null,
    val isReorderingPhotos: Boolean = false,
    /** The AI description sheet (#240, board VD-Saran-AI); null while closed. */
    val suggestion: SuggestionSheet? = null,
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

    /**
     * The AI needs a name, a category, and a condition, the fields the
     * server requires; the sales mode always has one. One request at a time.
     */
    val canSuggestDescription: Boolean
        get() = !isLoading && !isBusy && !lacksWhatTheAiNeeds && suggestion != SuggestionSheet.Loading

    /** The name, category, or condition is still missing; the button says so. */
    val lacksWhatTheAiNeeds: Boolean get() = name.isBlank() || categoryId == null || condition == null

    /** A saved product takes up to [MAX_PHOTOS], counting those on their way; a new one gets photos in #282. */
    val canAddPhoto: Boolean get() = isEdit && !isLoading && photos.size < MAX_PHOTOS

    /**
     * The order is sent whole, so it waits while an upload would add a photo
     * the list does not have yet, and while another reorder runs.
     */
    val canReorderPhotos: Boolean
        get() = !isReorderingPhotos && photos.none { it is PendingPhoto && it.status.isWaitingToSend }

    /** The server takes up to [MAX_VARIANTS]; one add at a time, so the count stays right. */
    val canAddVariant: Boolean get() = isEdit && !isAddingVariant && variants.size < MAX_VARIANTS

    /**
     * The last variant stays (#250); the seller switches the product off
     * instead. A variant already being deleted no longer counts.
     */
    val canDeleteVariant: Boolean get() = variants.count { variantBusy[it.id] != VariantBusy.Deleting } > 1

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

/** The most variants a product may have, as the server's SaveVariant allows. */
const val MAX_VARIANTS = 20

/** The typed fields of the variant sheet. */
enum class VariantField { Name, Price, MinQty, InitialStock }

/** What a variant row is waiting for. */
enum class VariantBusy { Saving, Deleting }

/**
 * The variant sheet as typed. [variantId] is null when adding, and only
 * then is [initialStock] asked; a saved variant's stock changes through the
 * stock book.
 */
data class VariantSheet(
    val variantId: Long? = null,
    val name: String = "",
    val unit: VariantUnit = VariantUnit.Kg,
    val price: String = "",
    val minQty: String = "1",
    val initialStock: String = "",
    val isActive: Boolean = true,
    val errors: Map<VariantField, UiText> = emptyMap(),
) {
    val isAdding: Boolean get() = variantId == null

    fun text(field: VariantField): String =
        when (field) {
            VariantField.Name -> name
            VariantField.Price -> price
            VariantField.MinQty -> minQty
            VariantField.InitialStock -> initialStock
        }

    /** [value] typed into [field], whose error clears. */
    fun withText(
        field: VariantField,
        value: String,
    ): VariantSheet {
        val changed =
            when (field) {
                VariantField.Name -> copy(name = value)
                VariantField.Price -> copy(price = value)
                VariantField.MinQty -> copy(minQty = value)
                VariantField.InitialStock -> copy(initialStock = value)
            }
        return changed.copy(errors = errors - field)
    }
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

    // The variants of a saved product (#239).

    data object AddVariant : ProductFormEvent

    data class EditVariant(
        val id: Long,
    ) : ProductFormEvent

    data class VariantSheetTextChanged(
        val field: VariantField,
        val value: String,
    ) : ProductFormEvent

    data class VariantSheetUnitPicked(
        val unit: VariantUnit,
    ) : ProductFormEvent

    data class VariantSheetActiveChanged(
        val active: Boolean,
    ) : ProductFormEvent

    /** Sends the sheet's variant; the sheet closes and the row shows its progress. */
    data object SaveVariant : ProductFormEvent

    data object DismissVariantSheet : ProductFormEvent

    /** Delete, from the edit sheet: asks first. */
    data object DeleteVariant : ProductFormEvent

    data object ConfirmDeleteVariant : ProductFormEvent

    data object DismissDeleteVariant : ProductFormEvent

    data object VariantMessageShown : ProductFormEvent

    // The AI description (#240).

    /** Asks the AI, or asks again from a failed sheet. */
    data object SuggestDescription : ProductFormEvent

    /** Fills the description and summary; over a written description, the first tap asks to confirm. */
    data object UseSuggestion : ProductFormEvent

    /** Closes the sheet and drops a request still running; the form is left as it was. */
    data object DismissSuggestion : ProductFormEvent

    // The photos of a saved product (#241).

    /** A photo from the crop screen, prepared in the cache at [path], to upload. */
    data class PhotoPrepared(
        val path: String,
    ) : ProductFormEvent

    data class RetryPhoto(
        val key: String,
    ) : ProductFormEvent

    /** Takes a photo that failed or waits off the row, with its cache file. */
    data class RemovePendingPhoto(
        val key: String,
    ) : ProductFormEvent

    /** Goes on with the photos a used-up limit held back. */
    data object ResumeUploads : ProductFormEvent

    data class OpenPhotoActions(
        val key: String,
    ) : ProductFormEvent

    data object DismissPhotoActions : ProductFormEvent

    data class MakeMainPhoto(
        val key: String,
    ) : ProductFormEvent

    /** A saved photo dragged from index [from] to [to] in the row. */
    data class MovePhoto(
        val from: Int,
        val to: Int,
    ) : ProductFormEvent

    /** Delete, from the actions sheet: asks first. */
    data class DeletePhoto(
        val key: String,
    ) : ProductFormEvent

    data object ConfirmDeletePhoto : ProductFormEvent

    data object DismissDeletePhoto : ProductFormEvent
}
