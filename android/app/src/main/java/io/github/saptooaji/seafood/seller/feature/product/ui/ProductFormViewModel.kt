package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDetail
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPhotoRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.pathTo
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

/**
 * Adding a product, or editing the one with the `productId` of the route.
 * What the seller typed is kept in the [SavedStateHandle], so it survives
 * rotation and process death; a recreated edit form loads the product again
 * for its variants and status, but not over the typed fields.
 */
@HiltViewModel
class ProductFormViewModel
    @Inject
    constructor(
        private val products: ProductRepository,
        private val categories: CategoryRepository,
        photoRepository: ProductPhotoRepository,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        /** Null while adding, until the new product is saved with photos still to upload (#282). */
        private var productId: Long? = savedStateHandle[KEY_PRODUCT_ID]

        private val _uiState = MutableStateFlow(ProductFormUiState(isEdit = productId != null))
        val uiState: StateFlow<ProductFormUiState> = _uiState.asStateFlow()

        /** The form as loaded, to tell whether Back throws anything away. */
        private var initial: List<Any?>? = null

        /** The variant rows of a saved product; adding has none to edit. */
        private var variants: VariantEditor? = productId?.let { VariantEditor(it, products, _uiState, viewModelScope) }

        /** The photos; while adding they wait for the save. */
        private val photos =
            PhotoEditor(productId, photoRepository, _uiState, viewModelScope, savedStateHandle, onQueueDone = ::afterUploads)

        /** The AI description (#240). */
        private val suggester = DescriptionSuggester(products, _uiState, viewModelScope, edit = ::edit)

        /** The parts with their own events, asked in turn before a field change. */
        private val parts: List<FormPart> get() = listOfNotNull(variants, photos, suggester)

        /** The product this form created, once saved with photos to upload; leaving then refreshes the list. */
        private var created: ProductDetail? = null

        init {
            load()
        }

        fun onEvent(event: ProductFormEvent) {
            when (event) {
                ProductFormEvent.Save -> save()
                ProductFormEvent.Back -> back()
                ProductFormEvent.ConfirmDiscard -> {
                    photos.discard()
                    _uiState.update { it.copy(showDiscardDialog = false, result = leaving) }
                }
                ProductFormEvent.DismissDiscard -> _uiState.update { it.copy(showDiscardDialog = false) }
                ProductFormEvent.Delete -> _uiState.update { it.copy(showDeleteDialog = it.isEdit && !it.isBusy) }
                ProductFormEvent.ConfirmDelete -> delete()
                ProductFormEvent.DismissDelete -> _uiState.update { it.copy(showDeleteDialog = false) }
                ProductFormEvent.Retry -> if (_uiState.value.loadError != null) load()
                else -> if (parts.none { it.handle(event) }) edit { it.changedBy(event) }
            }
        }

        /** Changes the form while it is shown, keeping the change in the handle. */
        private fun edit(change: (ProductFormUiState) -> ProductFormUiState) {
            if (_uiState.value.isLoading) return
            _uiState.update { change(it).copy(generalError = null) }
            savedStateHandle.store(_uiState.value)
        }

        private fun load() {
            _uiState.update { it.copy(isLoading = true, loadError = null) }
            viewModelScope.launch {
                val tree =
                    when (val result = categories.tree()) {
                        is ApiResult.Success -> result.data
                        else -> return@launch _uiState.update { it.copy(isLoading = false, loadError = result.message()) }
                    }
                val id = productId
                var loaded = ProductFormUiState(isEdit = id != null, isLoading = false, categories = tree)
                if (id != null) {
                    when (val result = products.get(id)) {
                        is ApiResult.Success -> loaded = loaded.filledFrom(result.data)
                        ApiResult.NotFound -> return@launch _uiState.update { it.copy(isLoading = false, notFound = true) }
                        else -> return@launch _uiState.update { it.copy(isLoading = false, loadError = result.message()) }
                    }
                }
                initial = loaded.values()
                _uiState.value = savedStateHandle.restoreWaitingPhotos(savedStateHandle.restoreInto(loaded))
            }
        }

        private fun save() {
            val state = _uiState.value
            if (state.isLoading || state.isBusy) return

            val (errors, checked) = state.validated()
            if (checked == null) {
                _uiState.update { it.withErrors(errors) }
                return
            }

            _uiState.update { it.copy(isSaving = true, errors = emptyMap(), generalError = null) }
            viewModelScope.launch {
                val id = productId
                val result =
                    if (id != null) {
                        products.update(id, checked.draft)
                    } else {
                        products.create(checked.draft, requireNotNull(checked.variant))
                    }
                if (id == null && result is ApiResult.Success && uploadsAfterSave(result.data)) return@launch
                _uiState.update {
                    val done = it.copy(isSaving = false)
                    if (result is ApiResult.Success) done.copy(result = ProductFormResult.Saved) else done.refusedBy(result)
                }
            }
        }

        /**
         * A new product is saved: its waiting photos go to it now, and the
         * form stays locked until they are done (#282). False when it has none.
         */
        private fun uploadsAfterSave(detail: ProductDetail): Boolean {
            val waiting = _uiState.value.photos.count { it is PendingPhoto && it.status == PendingStatus.AwaitingSave }
            if (waiting == 0) return false
            created = detail
            _uiState.update { it.copy(afterSaveTotal = waiting) }
            photos.uploadAfterSave(detail.id)
            return true
        }

        private fun delete() {
            val id = productId ?: return
            if (_uiState.value.isBusy) return
            _uiState.update { it.copy(showDeleteDialog = false, isDeleting = true, generalError = null) }
            viewModelScope.launch {
                val result = products.delete(id)
                _uiState.update { it.copy(isDeleting = false) }
                when (result) {
                    // Already gone is what the seller asked for.
                    is ApiResult.Success, ApiResult.NotFound -> _uiState.update { it.copy(result = ProductFormResult.Deleted) }
                    else -> _uiState.update { it.copy(generalError = result.message()) }
                }
            }
        }

        private fun back() {
            val state = _uiState.value
            val changed = initial != null && !state.notFound && state.values() != initial
            if (changed) {
                _uiState.update { it.copy(showDiscardDialog = true) }
            } else {
                _uiState.update { it.copy(result = leaving) }
            }
        }

        /** A product this form created is new to the list, however the form is left. */
        private val leaving: ProductFormResult
            get() =
                if (created != null ||
                    savedStateHandle.get<Boolean>(KEY_CREATED) == true
                ) {
                    ProductFormResult.Saved
                } else {
                    ProductFormResult.Left
                }

        /**
         * A new product's photos stopped uploading. All up: the form is done.
         * Some not: the product is saved, so the form becomes its edit form,
         * where those photos can be tried again or taken off (#282).
         */
        private fun afterUploads() {
            val detail = created
            if (detail == null || _uiState.value.afterSaveTotal == null) return
            val left = _uiState.value.photos.count { it is PendingPhoto }
            if (left == 0) {
                _uiState.update { it.copy(isSaving = false, afterSaveTotal = null, result = ProductFormResult.Saved) }
            } else {
                becomeEditForm(detail, left)
            }
        }

        /** The saved new product's edit form, with the photos [left] to try again or take off. */
        private fun becomeEditForm(
            detail: ProductDetail,
            left: Int,
        ) {
            productId = detail.id
            savedStateHandle[KEY_PRODUCT_ID] = detail.id
            savedStateHandle[KEY_CREATED] = true
            variants = VariantEditor(detail.id, products, _uiState, viewModelScope)
            _uiState.update {
                it.copy(
                    isEdit = true,
                    isSaving = false,
                    afterSaveTotal = null,
                    variants = detail.variants,
                    photoNotice = it.photoNotice ?: UiText.Resource(R.string.product_photo_saved_partly, listOf(left)),
                )
            }
            initial = _uiState.value.values()
        }

        override fun onCleared() {
            photos.discard()
        }

        companion object {
            /** The route's argument; absent when adding. */
            const val KEY_PRODUCT_ID = "productId"

            /** Set once a new product is saved and the form turned into its edit form. */
            private const val KEY_CREATED = "form_created"
        }
    }

/** A refused save on the form: field errors where the server names a field, else one message. */
private fun ProductFormUiState.refusedBy(result: ApiResult<*>): ProductFormUiState =
    when (result) {
        is ApiResult.Invalid -> {
            val errors = serverErrors(result.fieldErrors)
            if (errors.isEmpty()) {
                copy(generalError = result.message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server))
            } else {
                withErrors(errors)
            }
        }
        // Deleted meanwhile, maybe from the web: the form stays so nothing typed is lost.
        ApiResult.NotFound -> copy(generalError = UiText.Resource(R.string.product_form_not_found))
        else -> copy(generalError = result.message())
    }

/** The form after the seller typed or picked something; other events leave it as it is. */
private fun ProductFormUiState.changedBy(event: ProductFormEvent): ProductFormUiState =
    when (event) {
        is ProductFormEvent.TextChanged -> withText(event.field, event.value)
        is ProductFormEvent.AttributeChanged ->
            copy(attributeValues = attributeValues + (event.id to event.value), errors = errors - FormField.Attribute(event.id))
        is ProductFormEvent.CategoryPicked -> withCategory(event.id)
        is ProductFormEvent.ConditionPicked -> copy(condition = event.condition, errors = errors - FormField.Condition)
        is ProductFormEvent.SalesModePicked -> copy(salesMode = event.mode)
        is ProductFormEvent.ActiveChanged -> copy(isActive = event.active)
        is ProductFormEvent.VariantUnitPicked -> copy(variantUnit = event.unit)
        else -> this
    }

/** The errors shown, with a scroll to the first of them in the form's order. */
internal fun ProductFormUiState.withErrors(errors: Map<FormField, UiText>): ProductFormUiState {
    val first = fieldOrder().firstOrNull { it in errors } ?: errors.keys.firstOrNull()
    return copy(errors = errors, scrollTarget = first, scrollRequest = scrollRequest + 1)
}

/**
 * The category with [id] and its attributes, when it is a leaf. Values of
 * attributes the new category shares are kept; the rest are dropped.
 */
internal fun ProductFormUiState.withCategory(id: Long): ProductFormUiState {
    val path = categories.pathTo(id)
    val category = path.lastOrNull()?.takeIf { it.isLeaf } ?: return this
    val ids = category.attributes.map { it.id }.toSet()
    return copy(
        categoryId = id,
        categoryPath = path.map { it.name },
        attributes = category.attributes,
        attributeValues = attributeValues.filterKeys { it in ids },
        errors = errors.filterKeys { it != FormField.Category && it !is FormField.Attribute },
    )
}

private fun ProductFormUiState.filledFrom(detail: ProductDetail): ProductFormUiState {
    val draft = detail.draft
    val path = categories.pathTo(draft.categoryId)
    return copy(
        categoryId = draft.categoryId,
        categoryPath = path.map { it.name },
        attributes = path.lastOrNull()?.attributes.orEmpty(),
        attributeValues = draft.attributes,
        name = draft.name,
        description = draft.description,
        seoDescription = draft.seoDescription.orEmpty(),
        condition = draft.condition,
        salesMode = draft.salesMode,
        dailyQuota = draft.dailyQuota?.asTyped().orEmpty(),
        leadTimeDays = draft.leadTimeDays?.toString().orEmpty(),
        cutoffTime = draft.cutoffTime?.replace(':', '.').orEmpty(),
        shelfLifeDays = draft.shelfLifeDays?.toString().orEmpty(),
        storageInstructions = draft.storageInstructions.orEmpty(),
        isActive = draft.isActive,
        variants = detail.variants,
        photos = detail.images.map(::SavedPhoto),
        isBlocked = detail.isBlocked,
        blockReason = detail.blockReason,
    )
}

/**
 * The server's 422 keyed to the form. Its own Indonesian messages are shown
 * as they come; the variant row's, written for the API, are replaced.
 */
internal fun serverErrors(fieldErrors: Map<String, List<String>>): Map<FormField, UiText> =
    fieldErrors
        .mapNotNull { (key, messages) ->
            val message = messages.firstOrNull() ?: return@mapNotNull null
            val raw = UiText.Raw(message)
            when {
                key == "category_id" -> FormField.Category to raw
                key == "condition" -> FormField.Condition to raw
                key.startsWith("attributes.") -> key.removePrefix("attributes.").toLongOrNull()?.let { FormField.Attribute(it) to raw }
                key.startsWith("variants.") -> variantField(key)
                else -> SERVER_FIELDS[key]?.let { FormField.Text(it) to raw }
            }
        }.toMap()

private fun variantField(key: String): Pair<FormField, UiText>? {
    val (field, message) =
        when (key.substringAfterLast('.')) {
            "name" -> ProductTextField.VariantName to R.string.product_form_error_variant_name
            "price" -> ProductTextField.VariantPrice to R.string.product_form_error_price
            "min_qty" -> ProductTextField.VariantMinQty to R.string.product_form_error_decimal
            "initial_stock" -> ProductTextField.VariantStock to R.string.product_form_error_decimal
            else -> return null
        }
    return FormField.Text(field) to UiText.Resource(message)
}

private val SERVER_FIELDS =
    mapOf(
        "name" to ProductTextField.Name,
        "description" to ProductTextField.Description,
        "seo_description" to ProductTextField.SeoDescription,
        "daily_quota" to ProductTextField.DailyQuota,
        "lead_time_days" to ProductTextField.LeadTimeDays,
        "cutoff_time" to ProductTextField.CutoffTime,
        "shelf_life_days" to ProductTextField.ShelfLifeDays,
        "storage_instructions" to ProductTextField.StorageInstructions,
    )

/** What to tell the seller about a failed call. */
internal fun ApiResult<*>.message(): UiText =
    when (this) {
        ApiResult.NetworkError -> UiText.Resource(R.string.error_network)
        is ApiResult.Forbidden -> message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server)
        else -> UiText.Resource(R.string.error_server)
    }
