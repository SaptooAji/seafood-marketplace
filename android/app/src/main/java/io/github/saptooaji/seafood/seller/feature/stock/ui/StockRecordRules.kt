package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import java.math.BigDecimal

/** `RecordStockMovement`'s quantity: up to 7 digits and 3 decimals, a comma or a dot. */
private val QUANTITY = Regex("""-?\d{1,7}([.,]\d{1,3})?""")
private val ANY_NUMBER = Regex("""-?\d+([.,]\d+)?""")
private val COST = Regex("""\d[\d.]*""")
private const val MAX_DECIMALS = 3
private const val SUPPLIER_MAX = 255
private const val NOTE_MAX = 1000
private const val COST_MAX = 100_000_000L

/** The typed quantity as a number, or null while it is not one the server takes. */
fun recordQuantity(text: String): BigDecimal? {
    val trimmed = text.trim()
    return if (QUANTITY.matches(trimmed)) BigDecimal(trimmed.replace(',', '.')) else null
}

/**
 * The sheet's errors as `StockMovementRequest` and `RecordStockMovement`
 * would give them, and the change to send when there are none. The
 * supplier and cost go only with stock in.
 */
fun RecordSheet.validated(): Pair<Map<RecordField, UiText>, NewStockMovement?> {
    val stockIn = type == StockMovementType.StockIn
    val errors =
        buildMap {
            if (variantId == null) put(RecordField.Variant, UiText.Resource(R.string.stock_record_error_variant))
            quantityError(qty, type)?.let { put(RecordField.Qty, it) }
            if (stockIn && supplier.trim().length > SUPPLIER_MAX) {
                put(RecordField.Supplier, UiText.Resource(R.string.stock_record_error_too_long, listOf(SUPPLIER_MAX)))
            }
            if (stockIn &&
                unitCost.isNotBlank() &&
                cost(unitCost) == null
            ) {
                put(RecordField.UnitCost, UiText.Resource(R.string.stock_record_error_cost))
            }
            if (note.trim().length >
                NOTE_MAX
            ) {
                put(RecordField.Note, UiText.Resource(R.string.stock_record_error_too_long, listOf(NOTE_MAX)))
            }
        }
    val id = variantId
    val quantity = recordQuantity(qty)
    if (errors.isNotEmpty() || id == null || quantity == null) return errors to null
    return errors to
        NewStockMovement(
            variantId = id,
            type = type,
            qty = quantity,
            supplierName = supplier.trim().takeIf { stockIn && it.isNotEmpty() },
            unitCost = if (stockIn) cost(unitCost) else null,
            note = note.trim().ifEmpty { null },
        )
}

private fun quantityError(
    text: String,
    type: StockMovementType,
): UiText? {
    val trimmed = text.trim()
    val decimals = trimmed.substringAfterLast(',', trimmed.substringAfterLast('.', ""))
    val quantity = recordQuantity(trimmed)
    val message =
        when {
            trimmed.isEmpty() -> R.string.stock_record_error_qty
            !ANY_NUMBER.matches(trimmed) -> R.string.stock_error_number
            decimals.length > MAX_DECIMALS -> R.string.stock_error_decimals
            quantity == null -> R.string.stock_error_too_big
            quantity.signum() == 0 -> R.string.stock_record_error_zero
            quantity.signum() < 0 && type != StockMovementType.Adjustment -> R.string.stock_record_error_minus
            else -> return null
        }
    return UiText.Resource(message)
}

/** `140.000` is 140000; null when it is not a whole rupiah from 0 to 100 million. */
private fun cost(text: String): Long? {
    val trimmed = text.trim()
    if (!COST.matches(trimmed)) return null
    return trimmed.replace(".", "").toLongOrNull()?.takeIf { it <= COST_MAX }
}

/** A 422 on a field shows under it; anything else above the button. */
internal fun RecordSheet.refusedBy(result: ApiResult.Invalid): RecordSheet {
    val fields =
        buildMap {
            result.fieldErrors.forEach { (key, messages) ->
                val field = FIELD_KEYS[key]
                val message = messages.firstOrNull()
                if (field != null && message != null) put(field, UiText.Raw(message))
            }
        }
    val general =
        (
            result.message ?: result.fieldErrors.values
                .flatten()
                .firstOrNull()
        )?.let(UiText::Raw)
    return copy(isSaving = false, errors = fields, saveError = if (fields.isEmpty()) general else null)
}

/** A failed save; the sheet stays as typed. */
internal fun recordSaveMessage(result: ApiResult<*>): UiText =
    when (result) {
        ApiResult.NetworkError -> UiText.Resource(R.string.stock_record_save_network)
        is ApiResult.Forbidden -> result.message?.let(UiText::Raw) ?: UiText.Resource(R.string.stock_record_save_server)
        else -> UiText.Resource(R.string.stock_record_save_server)
    }

private val FIELD_KEYS =
    mapOf(
        "variant_id" to RecordField.Variant,
        "qty" to RecordField.Qty,
        "supplier_name" to RecordField.Supplier,
        "unit_cost" to RecordField.UnitCost,
        "note" to RecordField.Note,
    )
