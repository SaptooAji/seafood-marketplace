package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import java.math.BigDecimal

// From what was typed to what is sent, once [validated] found no errors.

/** What a valid form sends; [variant] is null while editing. */
data class CheckedProduct(
    val draft: ProductDraft,
    val variant: NewVariant?,
)

internal fun ProductFormUiState.draft(): ProductDraft {
    val preOrder = salesMode == SalesMode.PreOrder
    return ProductDraft(
        categoryId = requireNotNull(categoryId),
        name = name.trim(),
        description = description.trim(),
        seoDescription = seoDescription.trim().ifEmpty { null },
        condition = requireNotNull(condition),
        salesMode = salesMode,
        dailyQuota = if (preOrder) dailyQuota.trim().toDecimal() else null,
        leadTimeDays = if (preOrder) leadTimeDays.trim().toIntOrNull() else null,
        cutoffTime = if (preOrder) cutoffTime.trim().ifEmpty { null }?.replace('.', ':') else null,
        shelfLifeDays = shelfLifeDays.trim().toIntOrNull(),
        storageInstructions = storageInstructions.trim().ifEmpty { null },
        isActive = isActive,
        // Only the picked category's attributes; values of a category left behind are not sent.
        attributes =
            attributes
                .mapNotNull { attribute -> attributeValues[attribute.id]?.trim()?.takeIf { it.isNotEmpty() }?.let { attribute.id to it } }
                .toMap(),
    )
}

internal fun ProductFormUiState.variant() =
    NewVariant(
        name = variantName.trim(),
        unit = variantUnit,
        price = variantPrice.digitsOnly().toLong(),
        minQty = variantMinQty.trim().toDecimal(),
        initialStock = variantStock.trim().ifEmpty { null }?.toDecimal(),
    )

/** `25.000` from the server as the seller would type it: `25`, `12,5`. */
fun BigDecimal.asTyped(): String = stripTrailingZeros().toPlainString().replace('.', ',')

/** A price may be typed with thousand dots, such as `185.000`. */
internal fun String.digitsOnly(): String = trim().replace(".", "")

/** A number typed with a comma or a dot. */
internal fun String.toDecimal(): BigDecimal = BigDecimal(replace(',', '.'))

internal fun String.toDecimalOrNull(): BigDecimal? = replace(',', '.').toBigDecimalOrNull()
