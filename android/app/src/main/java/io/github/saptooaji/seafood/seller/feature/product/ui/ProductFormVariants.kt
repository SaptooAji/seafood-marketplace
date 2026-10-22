package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import kotlinx.coroutines.delay

// The variants of a saved product (#239): rows in the form (board
// VD-Produk-Form and its ukuran states), the add and edit sheet (VD-Varian),
// and the delete confirmation. Each action is saved on its own.

/** "4 dari 20", beside the section title. */
@Composable
fun VariantCount(uiState: ProductFormUiState) {
    Text(
        text = stringResource(R.string.product_form_variant_count, uiState.variants.size, MAX_VARIANTS),
        style = MaterialTheme.typography.labelMedium,
        color = EmbunColors.Muted,
    )
}

/** The rows, the add button, and what the seller should know about them. */
@Composable
fun ColumnScope.VariantList(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
) {
    uiState.variantNotice?.let { GlassBanner(text = it.asString(), tone = BannerTone.Warn) }
    uiState.variants.forEach { variant ->
        VariantRow(variant, uiState.variantBusy[variant.id], onClick = { onEvent(ProductFormEvent.EditVariant(variant.id)) })
    }
    GlassPillButton(
        text = stringResource(if (uiState.isAddingVariant) R.string.product_form_variant_adding else R.string.product_form_variant_add),
        onClick = { onEvent(ProductFormEvent.AddVariant) },
        enabled = uiState.canAddVariant,
        modifier = Modifier.testTag(ProductFormTags.ADD_VARIANT),
    )
    if (uiState.variants.size >= MAX_VARIANTS) {
        Text(
            text = stringResource(R.string.product_form_variant_full, MAX_VARIANTS),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
            color = EmbunColors.WarnContent,
        )
    }
    Text(
        text = stringResource(R.string.product_form_variants_stock_help),
        style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
        color = EmbunColors.Muted,
    )
}

/** One size: dimmed when it is not sold, and saying what it waits for while busy. */
@Composable
private fun VariantRow(
    variant: ProductVariant,
    busy: VariantBusy?,
    onClick: () -> Unit,
) {
    val unit = variant.unitLabel.lowercase()
    val detail =
        when (busy) {
            VariantBusy.Saving -> stringResource(R.string.product_form_variant_saving)
            VariantBusy.Deleting -> stringResource(R.string.product_form_variant_deleting)
            null -> {
                val stock =
                    stringResource(
                        R.string.product_form_variant_stock_min,
                        IndonesianNumbers.decimal(variant.stock),
                        unit,
                        IndonesianNumbers.decimal(variant.minQty),
                    )
                if (variant.isActive) stock else "$stock · ${stringResource(R.string.product_form_variant_inactive)}"
            }
        }
    Row(
        modifier =
            Modifier
                .fillMaxWidth()
                .background(VariantRowGlass, OptionShape)
                .clickable(enabled = busy == null, role = Role.Button, onClick = onClick)
                .padding(14.dp)
                .alpha(if (variant.isActive) 1f else INACTIVE_ALPHA)
                .testTag(ProductFormTags.variantRow(variant.id)),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = variant.name, style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
            Text(
                text = detail,
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = if (busy == null) EmbunColors.Muted else MaterialTheme.colorScheme.primary,
            )
        }
        Text(
            text = IndonesianNumbers.rupiah(variant.price),
            style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 16.sp),
        )
    }
}

/** The add or edit sheet of a variant (board VD-Varian). A saved variant's stock is shown, not edited. */
@Composable
fun BoxScope.VariantEditSheet(
    uiState: ProductFormUiState,
    sheet: VariantSheet,
    onEvent: (ProductFormEvent) -> Unit,
) {
    val unit = stringResource(sheet.unit.label).lowercase()
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissVariantSheet) }) {
        Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            val title =
                if (sheet.isAdding) R.string.product_form_variant_sheet_add_title else R.string.product_form_variant_sheet_edit_title
            SheetHeading(title = stringResource(title))
            SheetField(
                sheet,
                VariantField.Name,
                onEvent,
                label = stringResource(R.string.product_form_variant_sheet_name_label),
                tag = ProductFormTags.VARIANT_SHEET_NAME,
                keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences, imeAction = ImeAction.Next),
            )
            UnitChoice(sheet.unit, onEvent)
            SheetField(
                sheet,
                VariantField.Price,
                onEvent,
                label = stringResource(R.string.product_form_variant_sheet_price_label, unit),
                tag = ProductFormTags.VARIANT_SHEET_PRICE,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next),
                leading = stringResource(R.string.product_form_rupiah),
            )
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                SheetField(
                    sheet,
                    VariantField.MinQty,
                    onEvent,
                    label = stringResource(R.string.product_form_variant_min_qty_label),
                    tag = ProductFormTags.VARIANT_SHEET_MIN_QTY,
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
                    trailing = unit,
                    modifier = Modifier.weight(1f),
                )
                if (sheet.isAdding) {
                    SheetField(
                        sheet,
                        VariantField.InitialStock,
                        onEvent,
                        label = stringResource(R.string.product_form_variant_stock_label),
                        tag = ProductFormTags.VARIANT_SHEET_STOCK,
                        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Done),
                        trailing = unit,
                        modifier = Modifier.weight(1f),
                    )
                }
            }
            if (!sheet.isAdding) {
                uiState.variants.firstOrNull { it.id == sheet.variantId }?.let { StockLine(it) }
                SwitchRow(
                    title = stringResource(R.string.product_form_variant_sheet_active_title),
                    body = stringResource(R.string.product_form_variant_sheet_active_body),
                    checked = sheet.isActive,
                    onCheckedChange = { onEvent(ProductFormEvent.VariantSheetActiveChanged(it)) },
                    modifier = Modifier.testTag(ProductFormTags.VARIANT_SHEET_ACTIVE),
                )
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                if (!sheet.isAdding) {
                    DangerPillButton(
                        text = stringResource(R.string.product_form_variant_sheet_delete),
                        onClick = { onEvent(ProductFormEvent.DeleteVariant) },
                        enabled = uiState.canDeleteVariant,
                        modifier = Modifier.weight(1f).testTag(ProductFormTags.VARIANT_SHEET_DELETE),
                    )
                }
                PrimaryPillButton(
                    text = stringResource(R.string.product_form_variant_sheet_save),
                    onClick = { onEvent(ProductFormEvent.SaveVariant) },
                    modifier = Modifier.weight(if (sheet.isAdding) 1f else 2f).testTag(ProductFormTags.VARIANT_SHEET_SAVE),
                )
            }
        }
    }
}

@Suppress("LongParameterList") // The fields differ in these few settings only.
@Composable
private fun SheetField(
    sheet: VariantSheet,
    field: VariantField,
    onEvent: (ProductFormEvent) -> Unit,
    label: String,
    tag: String,
    keyboardOptions: KeyboardOptions,
    modifier: Modifier = Modifier,
    leading: String? = null,
    trailing: String? = null,
) {
    Column(modifier) {
        GlassTextField(
            value = sheet.text(field),
            onValueChange = { onEvent(ProductFormEvent.VariantSheetTextChanged(field, it)) },
            label = label,
            error = sheet.errors[field]?.asString(),
            leading = leading?.let { { FieldAffix(it) } },
            trailing = trailing?.let { { FieldAffix(it) } },
            keyboardOptions = keyboardOptions,
            modifier = Modifier.testTag(tag),
        )
    }
}

@Composable
private fun UnitChoice(
    selected: VariantUnit,
    onEvent: (ProductFormEvent) -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = stringResource(R.string.product_form_variant_sheet_unit_label),
            style = MaterialTheme.typography.labelMedium,
            color = EmbunColors.Muted,
        )
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            VariantUnit.entries.forEach { option ->
                ChoicePill(
                    text = stringResource(option.label),
                    selected = selected == option,
                    onClick = { onEvent(ProductFormEvent.VariantSheetUnitPicked(option)) },
                    modifier = Modifier.weight(1f).testTag(ProductFormTags.sheetUnit(option)),
                )
            }
        }
    }
}

/** "Stok 12 kg", and where it is changed instead. */
@Composable
private fun StockLine(variant: ProductVariant) {
    Column(
        modifier = Modifier.fillMaxWidth().background(VariantRowGlass, OptionShape).padding(14.dp),
        verticalArrangement = Arrangement.spacedBy(2.dp),
    ) {
        Text(
            text =
                stringResource(
                    R.string.product_form_variant_stock,
                    IndonesianNumbers.decimal(variant.stock),
                    variant.unitLabel.lowercase(),
                ),
            style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp),
        )
        Text(
            text = stringResource(R.string.product_form_variant_sheet_stock_help),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
            color = EmbunColors.Muted,
        )
    }
}

/** Asks before deleting [variant] (board VD-Varian--konfirmasi-hapus). */
@Composable
fun BoxScope.DeleteVariantSheet(
    variant: ProductVariant,
    onEvent: (ProductFormEvent) -> Unit,
) {
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissDeleteVariant) }) {
        SheetHeading(
            title = stringResource(R.string.product_form_variant_delete_title, variant.name),
            body = stringResource(R.string.product_form_variant_delete_body),
        )
        DangerPillButton(
            text = stringResource(R.string.product_form_variant_delete_confirm),
            onClick = { onEvent(ProductFormEvent.ConfirmDeleteVariant) },
            filled = true,
            modifier = Modifier.testTag(ProductFormTags.CONFIRM_DELETE_VARIANT),
        )
        GlassPillButton(
            text = stringResource(R.string.product_form_variant_delete_cancel),
            onClick = { onEvent(ProductFormEvent.DismissDeleteVariant) },
            modifier = Modifier.testTag(ProductFormTags.CANCEL_DELETE_VARIANT),
        )
    }
}

/** A short dark pill after a variant was saved, gone after a few seconds. */
@Composable
fun VariantMessage(
    message: UiText,
    onEvent: (ProductFormEvent) -> Unit,
) {
    LaunchedEffect(message) {
        delay(MESSAGE_MILLIS)
        onEvent(ProductFormEvent.VariantMessageShown)
    }
    Text(
        text = message.asString(),
        style = MaterialTheme.typography.bodyMedium,
        color = MaterialTheme.colorScheme.inverseOnSurface,
        modifier =
            Modifier
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.inverseSurface, PillShape)
                .padding(horizontal = 18.dp, vertical = 12.dp),
    )
}

private const val INACTIVE_ALPHA = 0.5f
private const val MESSAGE_MILLIS = 3_000L
private val VariantRowGlass = Color.White.copy(alpha = 0.6f)
