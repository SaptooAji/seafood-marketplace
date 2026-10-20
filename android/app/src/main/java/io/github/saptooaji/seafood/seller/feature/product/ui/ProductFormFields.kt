package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.unit.dp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit

/** A text field of the form bound to [field], with its error and its place for scrolling. */
@Suppress("LongParameterList") // The fields differ in these few settings only.
@Composable
fun FormTextField(
    uiState: ProductFormUiState,
    field: ProductTextField,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
    label: String,
    tag: String,
    keyboardOptions: KeyboardOptions,
    modifier: Modifier = Modifier,
    leading: String? = null,
    trailing: String? = null,
    helpText: String? = null,
    minLines: Int = 1,
) {
    Column(modifier.then(positions.of(FormField.Text(field)))) {
        GlassTextField(
            value = uiState.text(field),
            onValueChange = { onEvent(ProductFormEvent.TextChanged(field, it)) },
            label = label,
            error = uiState.errors[FormField.Text(field)]?.asString(),
            helpText = helpText,
            leading = leading?.let { { FieldAffix(it) } },
            trailing = trailing?.let { { FieldAffix(it) } },
            keyboardOptions = keyboardOptions,
            singleLine = minLines == 1,
            minLines = minLines,
            modifier = Modifier.testTag(tag),
        )
    }
}

/** A short word inside a field, before or after the text, such as `Rp` or `kg`. */
@Composable
fun FieldAffix(text: String) {
    Text(text = text, style = MaterialTheme.typography.bodyMedium, color = EmbunColors.Muted, maxLines = 1)
}

@Composable
fun FieldError(text: String) {
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = EmbunColors.DangerContent,
        modifier = Modifier.padding(start = 4.dp),
    )
}

val ProductCondition.label: Int
    get() =
        when (this) {
            ProductCondition.Live -> R.string.product_condition_live
            ProductCondition.Fresh -> R.string.product_condition_fresh
            ProductCondition.Frozen -> R.string.product_condition_frozen
            ProductCondition.Marinated -> R.string.product_condition_marinated
        }

val SalesMode.label: Int
    get() =
        when (this) {
            SalesMode.ReadyStock -> R.string.product_sales_mode_ready_stock
            SalesMode.PreOrder -> R.string.product_sales_mode_pre_order
        }

val VariantUnit.label: Int
    get() =
        when (this) {
            VariantUnit.Kg -> R.string.product_unit_kg
            VariantUnit.Piece -> R.string.product_unit_piece
            VariantUnit.Dozen -> R.string.product_unit_dozen
            VariantUnit.Pack -> R.string.product_unit_pack
        }
