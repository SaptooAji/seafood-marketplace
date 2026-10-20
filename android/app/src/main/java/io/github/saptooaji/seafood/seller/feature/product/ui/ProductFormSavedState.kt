package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.lifecycle.SavedStateHandle
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit

// The product form in the SavedStateHandle: only what the seller typed or
// picked, as Bundle types. Nothing here is secret; the bank account lives
// in another form.
private const val KEY_TOUCHED = "form_touched"
private const val KEY_CATEGORY = "form_category"
private const val KEY_ATTRIBUTE_IDS = "form_attribute_ids"
private const val KEY_ATTRIBUTE_VALUES = "form_attribute_values"
private const val KEY_CONDITION = "form_condition"
private const val KEY_SALES_MODE = "form_sales_mode"
private const val KEY_ACTIVE = "form_active"
private const val KEY_UNIT = "form_unit"

private fun textKey(field: ProductTextField) = "form_text_${field.name}"

internal fun SavedStateHandle.store(state: ProductFormUiState) {
    this[KEY_TOUCHED] = true
    this[KEY_CATEGORY] = state.categoryId
    val attributes = state.attributeValues.entries.toList()
    this[KEY_ATTRIBUTE_IDS] = attributes.map { it.key }.toLongArray()
    this[KEY_ATTRIBUTE_VALUES] = attributes.map { it.value }.toTypedArray()
    this[KEY_CONDITION] = state.condition?.name
    this[KEY_SALES_MODE] = state.salesMode.name
    this[KEY_ACTIVE] = state.isActive
    this[KEY_UNIT] = state.variantUnit.name
    ProductTextField.entries.forEach { this[textKey(it)] = state.text(it) }
}

/** [loaded] with what was typed before the form was recreated, if anything was. */
internal fun SavedStateHandle.restoreInto(loaded: ProductFormUiState): ProductFormUiState {
    if (get<Boolean>(KEY_TOUCHED) != true) return loaded

    var state = loaded
    val categoryId = get<Long>(KEY_CATEGORY)
    if (categoryId != null && categoryId != state.categoryId) state = state.withCategory(categoryId)
    val ids = get<LongArray>(KEY_ATTRIBUTE_IDS) ?: longArrayOf()
    val values = get<Array<String>>(KEY_ATTRIBUTE_VALUES) ?: emptyArray()
    val known = state.attributes.map { it.id }.toSet()
    state = state.copy(attributeValues = ids.zip(values).toMap().filterKeys { it in known })

    ProductTextField.entries.forEach { field -> get<String>(textKey(field))?.let { state = state.withText(field, it) } }
    return state.copy(
        condition = get<String>(KEY_CONDITION)?.let { name -> ProductCondition.entries.firstOrNull { it.name == name } },
        salesMode = get<String>(KEY_SALES_MODE)?.let { name -> SalesMode.entries.firstOrNull { it.name == name } } ?: state.salesMode,
        isActive = get<Boolean>(KEY_ACTIVE) ?: state.isActive,
        variantUnit = get<String>(KEY_UNIT)?.let { name -> VariantUnit.entries.firstOrNull { it.name == name } } ?: state.variantUnit,
    )
}
