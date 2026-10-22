package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/**
 * The variants of a saved product (#239), part of [ProductFormViewModel].
 * Each add, change, and delete goes to the server at once, apart from the
 * form's Save. Actions on different variants may run together, so every
 * answer updates the state as it is then, never a copy taken before.
 */
internal class VariantEditor(
    private val productId: Long,
    private val products: ProductRepository,
    private val state: MutableStateFlow<ProductFormUiState>,
    private val scope: CoroutineScope,
) {
    /** Handles a variant event; false for the form's other events. */
    fun handle(event: ProductFormEvent): Boolean {
        when (event) {
            ProductFormEvent.AddVariant -> open(id = null)
            is ProductFormEvent.EditVariant -> open(event.id)
            is ProductFormEvent.VariantSheetTextChanged -> state.update { s -> s.withSheet { it.withText(event.field, event.value) } }
            is ProductFormEvent.VariantSheetUnitPicked -> state.update { s -> s.withSheet { it.copy(unit = event.unit) } }
            is ProductFormEvent.VariantSheetActiveChanged -> state.update { s -> s.withSheet { it.copy(isActive = event.active) } }
            ProductFormEvent.SaveVariant -> save()
            ProductFormEvent.DismissVariantSheet -> state.update { it.copy(variantSheet = null) }
            ProductFormEvent.DeleteVariant -> askToDelete()
            ProductFormEvent.ConfirmDeleteVariant -> delete()
            ProductFormEvent.DismissDeleteVariant -> state.update { it.copy(variantToDelete = null) }
            ProductFormEvent.VariantMessageShown -> state.update { it.copy(variantMessage = null) }
            else -> return false
        }
        return true
    }

    /** The add sheet when [id] is null, or the edit sheet of an idle variant. */
    private fun open(id: Long?) {
        val current = state.value
        if (current.isLoading) return
        val sheet =
            when {
                id == null -> VariantSheet().takeIf { current.canAddVariant }
                id in current.variantBusy -> null
                else -> current.variants.firstOrNull { it.id == id }?.toSheet()
            } ?: return
        state.update { it.copy(variantSheet = sheet, variantNotice = null) }
    }

    private fun save() {
        val sheet = state.value.variantSheet ?: return
        val errors = sheet.errors()
        if (errors.isNotEmpty()) {
            state.update { it.copy(variantSheet = sheet.copy(errors = errors)) }
            return
        }
        val id = sheet.variantId
        if (id == null) add(sheet) else update(id, sheet)
    }

    private fun add(sheet: VariantSheet) {
        if (!state.value.canAddVariant) return
        state.update { it.copy(variantSheet = null, isAddingVariant = true, variantNotice = null) }
        scope.launch {
            val result = products.addVariant(productId, sheet.newVariant())
            state.update { it.copy(isAddingVariant = false) }
            when (result) {
                is ApiResult.Success ->
                    state.update {
                        it.copy(
                            variants = it.variants + result.data,
                            variantMessage = said(R.string.product_form_variant_added, result.data),
                        )
                    }
                else -> refused(result, sheet)
            }
        }
    }

    private fun update(
        id: Long,
        sheet: VariantSheet,
    ) {
        val variant = state.value.variants.firstOrNull { it.id == id } ?: return
        if (id in state.value.variantBusy) return
        state.update { it.copy(variantSheet = null, variantNotice = null, variantBusy = it.variantBusy + (id to VariantBusy.Saving)) }
        scope.launch {
            val result = products.updateVariant(id, sheet.changes())
            state.update { it.copy(variantBusy = it.variantBusy - id) }
            when (result) {
                is ApiResult.Success ->
                    state.update { current ->
                        current.copy(
                            variants = current.variants.map { if (it.id == id) result.data else it },
                            variantMessage = said(R.string.product_form_variant_saved, result.data),
                        )
                    }
                ApiResult.NotFound -> gone(variant)
                else -> refused(result, sheet)
            }
        }
    }

    private fun askToDelete() {
        val current = state.value
        // The add sheet has no variant id, so it finds none.
        val variant = current.variants.firstOrNull { it.id == current.variantSheet?.variantId }
        if (variant == null || !current.canDeleteVariant || variant.id in current.variantBusy) return
        state.update { it.copy(variantSheet = null, variantToDelete = variant) }
    }

    private fun delete() {
        val variant = state.value.variantToDelete ?: return
        state.update { it.copy(variantToDelete = null) }
        val current = state.value
        if (!current.canDeleteVariant || variant.id in current.variantBusy) return
        state.update { it.copy(variantNotice = null, variantBusy = it.variantBusy + (variant.id to VariantBusy.Deleting)) }
        scope.launch {
            val result = products.deleteVariant(variant.id)
            state.update { it.copy(variantBusy = it.variantBusy - variant.id) }
            when (result) {
                is ApiResult.Success ->
                    state.update { current ->
                        current.copy(
                            variants = current.variants.filterNot { it.id == variant.id },
                            variantMessage = said(R.string.product_form_variant_deleted, variant),
                        )
                    }
                ApiResult.NotFound -> gone(variant)
                // The last variant: another one was deleted elsewhere meanwhile (#250).
                is ApiResult.Invalid -> {
                    val message = result.fieldErrors["variant"]?.firstOrNull() ?: result.message
                    state.update { it.copy(variantNotice = message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server)) }
                    reload()
                }
                else -> state.update { it.copy(variantNotice = result.message()) }
            }
        }
    }

    /**
     * A refused add or change. Field errors open the sheet again with what
     * was typed, unless the seller already opened another one.
     */
    private fun refused(
        result: ApiResult<*>,
        sheet: VariantSheet,
    ) {
        val errors = if (result is ApiResult.Invalid) sheetErrors(result.fieldErrors) else emptyMap()
        state.update { current ->
            when {
                errors.isNotEmpty() && current.variantSheet == null -> current.copy(variantSheet = sheet.copy(errors = errors))
                result is ApiResult.Invalid ->
                    current.copy(variantNotice = result.message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server))
                else -> current.copy(variantNotice = result.message())
            }
        }
    }

    /** [variant] was deleted from another device: say so, and show the list as it is now. */
    private fun gone(variant: ProductVariant) {
        state.update { it.copy(variantNotice = UiText.Resource(R.string.product_form_variant_gone, listOf(variant.name))) }
        reload()
    }

    /** The variants again, leaving the rest of the form as typed. */
    private fun reload() {
        scope.launch {
            val result = products.get(productId)
            if (result is ApiResult.Success) state.update { it.copy(variants = result.data.variants) }
        }
    }
}

/** The open sheet changed by the seller; with no sheet open there is nothing to change. */
private fun ProductFormUiState.withSheet(change: (VariantSheet) -> VariantSheet): ProductFormUiState =
    variantSheet?.let { copy(variantSheet = change(it)) } ?: this

/** "Ukuran 1 kg disimpan.": [message] with the variant's name. */
private fun said(
    message: Int,
    variant: ProductVariant,
) = UiText.Resource(message, listOf(variant.name))

/** The server's 422 for a variant, keyed to the sheet; its Indonesian messages are shown as they come. */
private fun sheetErrors(fieldErrors: Map<String, List<String>>): Map<VariantField, UiText> =
    fieldErrors
        .mapNotNull { (key, messages) ->
            val field =
                when (key) {
                    "name" -> VariantField.Name
                    "price" -> VariantField.Price
                    "min_qty" -> VariantField.MinQty
                    "initial_stock" -> VariantField.InitialStock
                    else -> null
                }
            val message = messages.firstOrNull()
            if (field == null || message == null) null else field to UiText.Raw(message)
        }.toMap()
