package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import java.math.BigDecimal

/** `ConfirmDailyStockRequest`: 0 to 9.999.999,999, at most 3 decimals; a comma or a dot before them. */
private val COUNT = Regex("""\d{1,7}([.,]\d{1,3})?""")
private val ANY_NUMBER = Regex("""-?\d+([.,]\d+)?""")
private val REFUSED_COUNT = Regex("""^variants\.(\d+)\.""")
private val HALF = BigDecimal("0.5")
private const val MAX_DECIMALS = 3

/** A count for its field, without a thousands dot so it reads back the same: `1500`, `6,5`, `0`. */
fun fieldText(stock: BigDecimal): String {
    val plain = stock.stripTrailingZeros().toPlainString()
    // stripTrailingZeros keeps zero as 0.000 on old JDKs.
    return (if (stock.signum() == 0) "0" else plain).replace('.', ',')
}

/** The typed count as a number, or null when it is not a count the server takes. */
fun parseCount(text: String): BigDecimal? {
    val trimmed = text.trim()
    return if (COUNT.matches(trimmed)) BigDecimal(trimmed.replace(',', '.')) else null
}

/** Why a typed count is refused, or null when it is fine. */
fun countError(text: String): UiText? {
    val trimmed = text.trim()
    if (COUNT.matches(trimmed)) return null
    val decimals = trimmed.substringAfterLast(',', trimmed.substringAfterLast('.', ""))
    val message =
        when {
            !ANY_NUMBER.matches(trimmed) -> R.string.stock_error_number
            trimmed.startsWith("-") -> R.string.stock_error_negative
            decimals.length > MAX_DECIMALS -> R.string.stock_error_decimals
            else -> R.string.stock_error_too_big
        }
    return UiText.Resource(message)
}

/** What − and + change: half a kilo for kg, one for pieces, dozens, and packs. */
fun stepFor(unit: VariantUnit): BigDecimal = if (unit == VariantUnit.Kg) HALF else BigDecimal.ONE

/** Loading the screen failed: no internet, or anything else. */
internal fun loadMessage(result: ApiResult<*>): UiText =
    UiText.Resource(if (result == ApiResult.NetworkError) R.string.error_network else R.string.error_server)

/** A failed save; the counts stay on the screen. A suspended store hears the server's reason. */
internal fun saveMessage(result: ApiResult<*>): UiText =
    when (result) {
        ApiResult.NetworkError -> UiText.Resource(R.string.stock_save_network)
        is ApiResult.Forbidden -> result.message?.let(UiText::Raw) ?: UiText.Resource(R.string.stock_save_server)
        else -> UiText.Resource(R.string.stock_save_server)
    }

/**
 * A 422 on the save: `variants.N.stock` names the N-th of the [sent] counts,
 * so it shows on that row. Anything else shows above the button.
 */
internal fun StockTodayUiState.refusedBy(
    result: ApiResult.Invalid,
    sent: List<StockCount>,
): StockTodayUiState {
    val rows =
        buildMap {
            result.fieldErrors.forEach { (key, messages) ->
                val count = refusedIndex(key)?.let(sent::getOrNull)
                if (count != null) put(count.variantId, UiText.Raw(messages.firstOrNull() ?: result.message.orEmpty()))
            }
        }
    val firstError =
        result.message ?: result.fieldErrors.values
            .flatten()
            .firstOrNull()
    val general = firstError?.let(UiText::Raw) ?: UiText.Resource(R.string.stock_save_server)
    return copy(isSaving = false, serverErrors = rows, saveError = general.takeIf { rows.isEmpty() })
}

/** `variants.2.stock` is 2. */
private fun refusedIndex(key: String): Int? =
    REFUSED_COUNT
        .find(key)
        ?.groupValues
        ?.get(1)
        ?.toIntOrNull()
