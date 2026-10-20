package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode

// The limits of the server's ProductRequest, checked here first so the
// seller learns before sending.
private const val NAME_MAX = 255
private const val DESCRIPTION_MAX = 5000
private const val SEO_MAX = 160
private const val STORAGE_MAX = 2000
private const val VARIANT_NAME_MAX = 100
private const val LEAD_TIME_MAX = 255
private const val SHELF_LIFE_MAX = 65535
private const val PRICE_MAX = 100_000_000L

/** Up to 7 whole digits and 3 decimals, with a dot or a comma: `decimal(10,3)`. */
private val DECIMAL = Regex("""\d{1,7}([.,]\d{1,3})?""")
private val TIME = Regex("""([01]\d|2[0-3])[.:]([0-5]\d)""")
private val WHOLE = Regex("""\d{1,6}""")

/** The fields in the order the form shows them, to scroll to the first error. */
fun ProductFormUiState.fieldOrder(): List<FormField> =
    listOf(FormField.Category) +
        attributes.map { FormField.Attribute(it.id) } +
        listOf(
            FormField.Text(ProductTextField.Name),
            FormField.Text(ProductTextField.Description),
            FormField.Text(ProductTextField.SeoDescription),
            FormField.Condition,
        ) +
        listOf(
            ProductTextField.DailyQuota,
            ProductTextField.LeadTimeDays,
            ProductTextField.CutoffTime,
            ProductTextField.ShelfLifeDays,
            ProductTextField.StorageInstructions,
            ProductTextField.VariantName,
            ProductTextField.VariantPrice,
            ProductTextField.VariantMinQty,
            ProductTextField.VariantStock,
        ).map { FormField.Text(it) }

/** The errors of the form, and what to send when there are none. */
fun ProductFormUiState.validated(): Pair<Map<FormField, UiText>, CheckedProduct?> {
    val errors = kindErrors() + storyErrors() + sellingErrors() + if (isEdit) emptyMap() else variantErrors()
    if (errors.isNotEmpty()) return errors to null
    return errors to CheckedProduct(draft(), if (isEdit) null else variant())
}

private fun ProductFormUiState.kindErrors(): Map<FormField, UiText> =
    buildMap {
        if (categoryId == null) put(FormField.Category, UiText.Resource(R.string.product_form_error_category_required))
        attributes.forEach { attribute ->
            val value = attributeValues[attribute.id].orEmpty().trim()
            val message =
                when {
                    value.isEmpty() -> if (attribute.isRequired) R.string.product_form_error_attribute_required else null
                    attribute.type == AttributeType.Number && value.toDecimalOrNull() == null ->
                        R.string.product_form_error_attribute_number
                    else -> null
                }
            if (message != null) put(FormField.Attribute(attribute.id), UiText.Resource(message))
        }
    }

private fun ProductFormUiState.storyErrors(): Map<FormField, UiText> =
    buildMap {
        when {
            name.isBlank() -> refuse(ProductTextField.Name, R.string.product_form_error_name_required)
            name.trim().length > NAME_MAX -> refuse(ProductTextField.Name, R.string.product_form_error_name_too_long)
        }
        when {
            description.isBlank() -> refuse(ProductTextField.Description, R.string.product_form_error_description_required)
            description.trim().length > DESCRIPTION_MAX ->
                refuse(ProductTextField.Description, R.string.product_form_error_too_long, DESCRIPTION_MAX)
        }
        if (seoDescription.trim().length > SEO_MAX) refuse(ProductTextField.SeoDescription, R.string.product_form_error_too_long, SEO_MAX)
        if (condition == null) put(FormField.Condition, UiText.Resource(R.string.product_form_error_condition_required))
    }

private fun ProductFormUiState.sellingErrors(): Map<FormField, UiText> =
    buildMap {
        if (salesMode == SalesMode.PreOrder) {
            when {
                dailyQuota.isBlank() -> refuse(ProductTextField.DailyQuota, R.string.product_form_error_quota_required)
                !dailyQuota.trim().matches(DECIMAL) -> refuse(ProductTextField.DailyQuota, R.string.product_form_error_decimal)
            }
            if (!leadTimeDays.isWholeUpTo(LEAD_TIME_MAX)) {
                refuse(ProductTextField.LeadTimeDays, R.string.product_form_error_days, LEAD_TIME_MAX)
            }
            if (cutoffTime.isNotBlank() && !cutoffTime.trim().matches(TIME)) {
                refuse(ProductTextField.CutoffTime, R.string.product_form_error_cutoff)
            }
        }
        if (!shelfLifeDays.isWholeUpTo(SHELF_LIFE_MAX)) {
            refuse(ProductTextField.ShelfLifeDays, R.string.product_form_error_days, SHELF_LIFE_MAX)
        }
        if (storageInstructions.trim().length > STORAGE_MAX) {
            refuse(ProductTextField.StorageInstructions, R.string.product_form_error_too_long, STORAGE_MAX)
        }
    }

private fun ProductFormUiState.variantErrors(): Map<FormField, UiText> =
    buildMap {
        when {
            variantName.isBlank() -> refuse(ProductTextField.VariantName, R.string.product_form_error_variant_name)
            variantName.trim().length > VARIANT_NAME_MAX ->
                refuse(ProductTextField.VariantName, R.string.product_form_error_too_long, VARIANT_NAME_MAX)
        }
        val price = variantPrice.digitsOnly().toLongOrNull()
        if (price == null || price < 1 || price > PRICE_MAX) refuse(ProductTextField.VariantPrice, R.string.product_form_error_price)
        val minQty = variantMinQty.trim()
        if (!minQty.matches(DECIMAL) || minQty.toDecimal().signum() == 0) {
            refuse(ProductTextField.VariantMinQty, R.string.product_form_error_decimal)
        }
        if (variantStock.isNotBlank() && !variantStock.trim().matches(DECIMAL)) {
            refuse(ProductTextField.VariantStock, R.string.product_form_error_decimal)
        }
    }

private fun MutableMap<FormField, UiText>.refuse(
    field: ProductTextField,
    message: Int,
    vararg args: Any,
) {
    put(FormField.Text(field), UiText.Resource(message, args.toList()))
}

private fun String.isWholeUpTo(max: Int): Boolean {
    val value = trim()
    return value.isEmpty() || (value.matches(WHOLE) && value.toInt() <= max)
}
