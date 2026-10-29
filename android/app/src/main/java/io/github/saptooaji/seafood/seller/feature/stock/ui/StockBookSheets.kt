package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ExperimentalLayoutApi
import androidx.compose.foundation.layout.FlowRow
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.error
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Primary100
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.feature.product.ui.ChoicePill
import io.github.saptooaji.seafood.seller.feature.product.ui.EmbunSheet
import io.github.saptooaji.seafood.seller.feature.product.ui.SheetHeading
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType

/** The board VD-Buku-Stok--saring: every type, a variant, and a date range. */
@OptIn(ExperimentalLayoutApi::class)
@Composable
internal fun BoxScope.FilterSheet(
    draft: FilterDraft,
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    EmbunSheet(onDismiss = { onEvent(StockBookEvent.CloseFilter) }) {
        SheetTop(stringResource(R.string.stock_filter_title), StockBookTags.CLOSE_FILTER) { onEvent(StockBookEvent.CloseFilter) }
        Column(
            Modifier.heightIn(max = SHEET_BODY).verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            SheetLabel(stringResource(R.string.stock_filter_type))
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                ChoicePill(
                    text = stringResource(R.string.stock_book_all_types),
                    selected = draft.type == null,
                    onClick = { onEvent(StockBookEvent.FilterTypePicked(null)) },
                    modifier = Modifier.testTag(StockBookTags.filterType(null)),
                )
                uiState.filterTypes.forEach { type ->
                    ChoicePill(
                        text = stringResource(typeName(type)),
                        selected = draft.type == type,
                        onClick = { onEvent(StockBookEvent.FilterTypePicked(type)) },
                        modifier = Modifier.testTag(StockBookTags.filterType(type)),
                    )
                }
            }
            SheetLabel(stringResource(R.string.stock_filter_variant))
            FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                ChoicePill(
                    text = stringResource(R.string.stock_book_all_variants),
                    selected = draft.variantId == null,
                    onClick = { onEvent(StockBookEvent.FilterVariantPicked(null)) },
                    modifier = Modifier.testTag(StockBookTags.filterVariant(null)),
                )
                uiState.products.flatMap { it.variants }.forEach { variant ->
                    ChoicePill(
                        text = uiState.variantTitle(variant.id).orEmpty(),
                        selected = draft.variantId == variant.id,
                        onClick = { onEvent(StockBookEvent.FilterVariantPicked(variant.id)) },
                        modifier = Modifier.testTag(StockBookTags.filterVariant(variant.id)),
                    )
                }
            }
            DateRange(draft, onEvent)
            PrimaryPillButton(
                text = stringResource(R.string.stock_filter_apply),
                onClick = { onEvent(StockBookEvent.ApplyFilter) },
                enabled = draft.error == null,
                modifier = Modifier.testTag(StockBookTags.APPLY_FILTER),
            )
            TextButton(onClick = { onEvent(StockBookEvent.ClearFilter) }, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                Text(text = stringResource(R.string.stock_book_clear_filter), fontWeight = FontWeight.ExtraBold)
            }
        }
    }
}

@Composable
private fun DateRange(
    draft: FilterDraft,
    onEvent: (StockBookEvent) -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            DateField(stringResource(R.string.stock_filter_from), draft.from, StockBookTags.FILTER_FROM, Modifier.weight(1f)) {
                onEvent(StockBookEvent.FilterDatesChanged(it, draft.to))
            }
            DateField(
                stringResource(R.string.stock_filter_to),
                draft.to,
                StockBookTags.FILTER_TO,
                Modifier.weight(1f),
                refused = draft.error != null,
            ) { onEvent(StockBookEvent.FilterDatesChanged(draft.from, it)) }
        }
        draft.error?.let {
            Text(text = it.asString(), style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp), color = EmbunColors.DangerContent)
        }
    }
}

/** The board VD-Catat-Stok and its states; the variant list opens over it. */
@Composable
internal fun BoxScope.RecordSheetView(
    sheet: RecordSheet,
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    EmbunSheet(onDismiss = { onEvent(if (sheet.pickingVariant) StockBookEvent.CloseVariantPicker else StockBookEvent.CloseRecord) }) {
        if (sheet.pickingVariant) {
            VariantPicker(sheet, uiState, onEvent)
            return@EmbunSheet
        }
        SheetTop(stringResource(R.string.stock_record_title), StockBookTags.CLOSE_RECORD) { onEvent(StockBookEvent.CloseRecord) }
        Column(
            Modifier.heightIn(max = SHEET_BODY).verticalScroll(rememberScrollState()),
            verticalArrangement = Arrangement.spacedBy(16.dp),
        ) {
            TypeTiles(sheet, uiState.recordTypes, onEvent)
            VariantField(sheet, uiState, onEvent)
            RecordFields(sheet, onEvent)
            sheet.saveError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
            PrimaryPillButton(
                text = stringResource(if (sheet.isSaving) R.string.stock_saving else R.string.stock_record_save),
                onClick = { onEvent(StockBookEvent.SaveRecord) },
                enabled = !sheet.isSaving,
                modifier = Modifier.testTag(StockBookTags.SAVE_RECORD),
            )
        }
    }
}

/** Two by two: Stok masuk, Penjualan offline, Susut, Penyesuaian. */
@Composable
private fun TypeTiles(
    sheet: RecordSheet,
    types: List<StockMovementType>,
    onEvent: (StockBookEvent) -> Unit,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        types.chunked(2).forEach { row ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                row.forEach { type ->
                    val chosen = sheet.type == type
                    Column(
                        modifier =
                            Modifier
                                .weight(1f)
                                .heightIn(min = 58.dp)
                                .background(
                                    if (chosen) MaterialTheme.colorScheme.onBackground else Color.White.copy(alpha = 0.65f),
                                    OptionShape,
                                ).clickable(role = Role.RadioButton) { onEvent(StockBookEvent.RecordTypePicked(type)) }
                                .semantics { selected = chosen }
                                .testTag(StockBookTags.recordType(type))
                                .padding(horizontal = 12.dp, vertical = 8.dp),
                        verticalArrangement = Arrangement.spacedBy(2.dp, Alignment.CenterVertically),
                    ) {
                        val color = if (chosen) Color.White else MaterialTheme.colorScheme.onBackground
                        Text(text = stringResource(typeName(type)), fontWeight = FontWeight.Bold, fontSize = 15.sp, color = color)
                        Text(text = stringResource(typeHint(type)), fontSize = 12.sp, color = color.copy(alpha = 0.75f))
                    }
                }
            }
        }
    }
}

@Composable
private fun VariantField(
    sheet: RecordSheet,
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    val error = sheet.errors[RecordField.Variant]?.asString()
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp)
                    .background(EmbunColors.Field, OptionShape)
                    .border(1.5.dp, if (error != null) EmbunColors.DangerContent else EmbunColors.FieldEdge, OptionShape)
                    .clickable(role = Role.Button) { onEvent(StockBookEvent.OpenVariantPicker) }
                    .semantics(mergeDescendants = true) { if (error != null) error(error) }
                    .testTag(StockBookTags.RECORD_VARIANT)
                    .padding(horizontal = 18.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            val title = uiState.variantTitle(sheet.variantId)
            Text(
                text = title ?: stringResource(R.string.stock_record_pick_variant),
                color = if (title == null) EmbunColors.Muted else MaterialTheme.colorScheme.onSurface,
                fontSize = 15.sp,
                modifier = Modifier.weight(1f),
            )
            Icon(
                painterResource(R.drawable.ic_chevron_down),
                contentDescription = null,
                tint = EmbunColors.Muted,
                modifier = Modifier.size(18.dp),
            )
        }
        error?.let { Text(text = it, style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp), color = EmbunColors.DangerContent) }
    }
}

/** The quantity with the stock it leaves, the supplier and cost for stock in, and a note. */
@Composable
private fun RecordFields(
    sheet: RecordSheet,
    onEvent: (StockBookEvent) -> Unit,
) {
    val unit =
        sheet.variant
            ?.unitLabel
            ?.lowercase()
            .orEmpty()
    val change = { field: RecordField -> { text: String -> onEvent(StockBookEvent.RecordTextChanged(field, text)) } }
    val help =
        listOfNotNull(
            if (sheet.type == StockMovementType.Adjustment) stringResource(R.string.stock_record_minus_hint) else null,
            sheet.preview?.asString(),
        ).joinToString(" ").ifEmpty { null }
    GlassTextField(
        value = sheet.qty,
        onValueChange = change(RecordField.Qty),
        label = stringResource(R.string.stock_record_qty),
        error = sheet.errors[RecordField.Qty]?.asString(),
        helpText = help,
        trailing = if (unit.isEmpty()) null else ({ Text(unit, color = EmbunColors.Muted) }),
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
        modifier = Modifier.testTag(StockBookTags.RECORD_QTY),
    )
    if (sheet.type == StockMovementType.StockIn) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            Box(Modifier.weight(1f)) {
                GlassTextField(
                    value = sheet.supplier,
                    onValueChange = change(RecordField.Supplier),
                    label = stringResource(R.string.stock_record_supplier),
                    error = sheet.errors[RecordField.Supplier]?.asString(),
                    modifier = Modifier.testTag(StockBookTags.RECORD_SUPPLIER),
                )
            }
            Box(Modifier.weight(1f)) {
                GlassTextField(
                    value = sheet.unitCost,
                    onValueChange = change(RecordField.UnitCost),
                    label =
                        if (unit.isEmpty()) {
                            stringResource(
                                R.string.stock_record_cost,
                            )
                        } else {
                            stringResource(R.string.stock_record_cost_per, unit)
                        },
                    error = sheet.errors[RecordField.UnitCost]?.asString(),
                    leading = { Text(stringResource(R.string.product_form_rupiah), color = EmbunColors.Muted) },
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                    modifier = Modifier.testTag(StockBookTags.RECORD_COST),
                )
            }
        }
    }
    GlassTextField(
        value = sheet.note,
        onValueChange = change(RecordField.Note),
        label = stringResource(R.string.stock_record_note),
        error = sheet.errors[RecordField.Note]?.asString(),
        modifier = Modifier.testTag(StockBookTags.RECORD_NOTE),
    )
}

/** The board VD-Catat-Stok--pilih-ukuran: the active variants with their stock. */
@Composable
private fun VariantPicker(
    sheet: RecordSheet,
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    SheetTop(stringResource(R.string.stock_record_pick_variant), StockBookTags.CLOSE_PICKER) { onEvent(StockBookEvent.CloseVariantPicker) }
    Column(Modifier.heightIn(max = SHEET_BODY).verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        uiState.products.forEach { product ->
            Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(text = product.name, fontWeight = FontWeight.Bold, fontSize = 15.sp, modifier = Modifier.padding(start = 4.dp))
                product.variants.forEach { variant ->
                    val chosen = sheet.variantId == variant.id
                    Row(
                        modifier =
                            Modifier
                                .fillMaxWidth()
                                .heightIn(min = 52.dp)
                                .background(if (chosen) Primary100.copy(alpha = 0.9f) else EmbunColors.Field, OptionShape)
                                .clickable(role = Role.Button) { onEvent(StockBookEvent.RecordVariantPicked(variant.id)) }
                                .semantics(mergeDescendants = true) { selected = chosen }
                                .testTag(StockBookTags.pickVariant(variant.id))
                                .padding(horizontal = 18.dp),
                        verticalAlignment = Alignment.CenterVertically,
                        horizontalArrangement = Arrangement.spacedBy(10.dp),
                    ) {
                        Text(text = variant.name, fontWeight = FontWeight.SemiBold, fontSize = 15.sp, modifier = Modifier.weight(1f))
                        Text(
                            text = IndonesianNumbers.decimal(variant.stock) + " " + variant.unitLabel.lowercase(),
                            fontSize = 13.sp,
                            color = EmbunColors.Muted,
                        )
                    }
                }
            }
        }
    }
}

/** A sheet's title with its round close button. */
@Composable
private fun SheetTop(
    title: String,
    closeTag: String,
    onClose: () -> Unit,
) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        SheetHeading(title = title, modifier = Modifier.weight(1f))
        Surface(
            onClick = onClose,
            shape = CircleShape,
            color = EmbunColors.GlassStrong,
            modifier = Modifier.size(44.dp).testTag(closeTag),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    painterResource(R.drawable.ic_close),
                    contentDescription = stringResource(R.string.stock_sheet_close),
                    modifier = Modifier.size(22.dp),
                )
            }
        }
    }
}

@Composable
private fun SheetLabel(text: String) {
    Text(text = text, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
}

private val SHEET_BODY = 620.dp
