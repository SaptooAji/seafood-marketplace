package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.error
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassSectionHeader
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit

// The parts of the product form as the board VD-Produk-Form numbers them,
// without the photos (#241): kind, name and story, condition and selling,
// sizes and prices, then the switch to show it to buyers.

private const val SEO_MAX = 160

@Composable
fun CategorySection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    onPickCategory: () -> Unit,
    positions: FieldPositions,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 1, title = stringResource(R.string.product_form_section_category))
        CategoryField(uiState, onPickCategory, positions.of(FormField.Category))
        uiState.attributes.forEach { attribute ->
            AttributeField(
                attribute = attribute,
                value = uiState.attributeValues[attribute.id].orEmpty(),
                error = uiState.errors[FormField.Attribute(attribute.id)]?.asString(),
                onValueChange = { onEvent(ProductFormEvent.AttributeChanged(attribute.id, it)) },
                modifier = positions.of(FormField.Attribute(attribute.id)),
            )
        }
    }
}

@Composable
private fun CategoryField(
    uiState: ProductFormUiState,
    onPickCategory: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val error = uiState.errors[FormField.Category]?.asString()
    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 56.dp)
                    .background(EmbunColors.Field, OptionShape)
                    .border(1.5.dp, if (error != null) EmbunColors.DangerContent else EmbunColors.FieldEdge, OptionShape)
                    .clickable(role = Role.Button, onClick = onPickCategory)
                    .semantics { if (error != null) error(error) }
                    .testTag(ProductFormTags.CATEGORY)
                    .padding(horizontal = 18.dp, vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(
                    text = stringResource(R.string.product_form_category_label),
                    style = MaterialTheme.typography.labelMedium.copy(fontSize = 12.sp),
                    color = EmbunColors.Muted,
                )
                Text(
                    text = uiState.categoryPath.joinToString(" › ").ifEmpty { stringResource(R.string.product_form_category_placeholder) },
                    style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold),
                    color = if (uiState.categoryPath.isEmpty()) EmbunColors.Muted else MaterialTheme.colorScheme.onSurface,
                )
            }
            Icon(
                painter = painterResource(R.drawable.ic_chevron_right),
                contentDescription = null,
                tint = EmbunColors.Muted,
                modifier = Modifier.size(20.dp),
            )
        }
        if (error != null) FieldError(error)
    }
}

@Composable
fun StorySection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 2, title = stringResource(R.string.product_form_section_story))
        FormTextField(
            uiState,
            ProductTextField.Name,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_name_label),
            tag = ProductFormTags.NAME,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
        )
        FormTextField(
            uiState,
            ProductTextField.Description,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_description_label),
            tag = ProductFormTags.DESCRIPTION,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 4,
        )
        FormTextField(
            uiState,
            ProductTextField.SeoDescription,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_seo_label),
            tag = ProductFormTags.SEO,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 2,
            helpText = stringResource(R.string.product_form_counter, uiState.seoDescription.length, SEO_MAX),
        )
    }
}

@Composable
fun SellingSection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 3, title = stringResource(R.string.product_form_section_selling))
        ConditionChoice(uiState, onEvent, positions.of(FormField.Condition))
        SegmentedChoice(
            options = SalesMode.entries,
            selected = uiState.salesMode,
            label = { stringResource(it.label) },
            onSelect = { onEvent(ProductFormEvent.SalesModePicked(it)) },
            tag = ProductFormTags::salesMode,
        )
        if (uiState.salesMode == SalesMode.PreOrder) PreOrderFields(uiState, onEvent, positions)
        FormTextField(
            uiState,
            ProductTextField.ShelfLifeDays,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_shelf_life_label),
            tag = ProductFormTags.SHELF_LIFE,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next),
            trailing = stringResource(R.string.product_form_days),
        )
        FormTextField(
            uiState,
            ProductTextField.StorageInstructions,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_storage_label),
            tag = ProductFormTags.STORAGE,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 2,
        )
    }
}

@Composable
private fun ConditionChoice(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            ProductCondition.entries.forEach { condition ->
                ChoicePill(
                    text = stringResource(condition.label),
                    selected = uiState.condition == condition,
                    onClick = { onEvent(ProductFormEvent.ConditionPicked(condition)) },
                    modifier = Modifier.weight(1f).testTag(ProductFormTags.condition(condition)),
                )
            }
        }
        uiState.errors[FormField.Condition]?.let { FieldError(it.asString()) }
    }
}

@Composable
private fun PreOrderFields(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
) {
    // The quota counts in the product's unit: the first size's when editing.
    val unit = uiState.variants.firstOrNull()?.unitLabel ?: stringResource(uiState.variantUnit.label)
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        FormTextField(
            uiState,
            ProductTextField.DailyQuota,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_quota_label),
            tag = ProductFormTags.QUOTA,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
            trailing = unit.lowercase(),
            modifier = Modifier.weight(1f),
        )
        FormTextField(
            uiState,
            ProductTextField.LeadTimeDays,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_lead_time_label),
            tag = ProductFormTags.LEAD_TIME,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next),
            modifier = Modifier.weight(1f),
        )
        FormTextField(
            uiState,
            ProductTextField.CutoffTime,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_cutoff_label),
            tag = ProductFormTags.CUTOFF,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
            modifier = Modifier.weight(1f),
        )
    }
}

@Composable
fun VariantSection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 4, title = stringResource(R.string.product_form_section_variants))
        if (uiState.isEdit) {
            uiState.variants.forEach { VariantRow(it) }
            Text(
                text = stringResource(R.string.product_form_variants_stock_help),
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
            )
        } else {
            FirstVariantFields(uiState, onEvent, positions)
        }
    }
}

@Composable
private fun FirstVariantFields(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    positions: FieldPositions,
) {
    val unit = stringResource(uiState.variantUnit.label).lowercase()
    Text(
        text = stringResource(R.string.product_form_variant_first_help),
        style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
        color = EmbunColors.Muted,
    )
    FormTextField(
        uiState,
        ProductTextField.VariantName,
        onEvent,
        positions,
        label = stringResource(R.string.product_form_variant_name_label),
        tag = ProductFormTags.VARIANT_NAME,
        keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences, imeAction = ImeAction.Next),
    )
    FormTextField(
        uiState,
        ProductTextField.VariantPrice,
        onEvent,
        positions,
        label = stringResource(R.string.product_form_variant_price_label),
        tag = ProductFormTags.VARIANT_PRICE,
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next),
        leading = stringResource(R.string.product_form_rupiah),
    )
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(
            text = stringResource(R.string.product_form_variant_unit_label),
            style = MaterialTheme.typography.labelMedium,
            color = EmbunColors.Muted,
        )
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            VariantUnit.entries.forEach { option ->
                ChoicePill(
                    text = stringResource(option.label),
                    selected = uiState.variantUnit == option,
                    onClick = { onEvent(ProductFormEvent.VariantUnitPicked(option)) },
                    modifier = Modifier.weight(1f).testTag(ProductFormTags.unit(option)),
                )
            }
        }
    }
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        FormTextField(
            uiState,
            ProductTextField.VariantMinQty,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_variant_min_qty_label),
            tag = ProductFormTags.VARIANT_MIN_QTY,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Next),
            trailing = unit,
            modifier = Modifier.weight(1f),
        )
        FormTextField(
            uiState,
            ProductTextField.VariantStock,
            onEvent,
            positions,
            label = stringResource(R.string.product_form_variant_stock_label),
            tag = ProductFormTags.VARIANT_STOCK,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Done),
            trailing = unit,
            modifier = Modifier.weight(1f),
        )
    }
}

/** A size of the product, read only until #239 brings its editor. */
@Composable
private fun VariantRow(variant: ProductVariant) {
    Row(
        modifier =
            Modifier
                .fillMaxWidth()
                .background(VariantRowGlass, OptionShape)
                .padding(14.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = variant.name, style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
            Text(
                text =
                    stringResource(
                        R.string.product_form_variant_stock,
                        IndonesianNumbers.decimal(variant.stock),
                        variant.unitLabel.lowercase(),
                    ),
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
            )
        }
        Text(
            text = IndonesianNumbers.rupiah(variant.price),
            style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 16.sp),
        )
    }
}

@Composable
fun ActiveSection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth(), contentPadding = PaddingValues(16.dp)) {
        SwitchRow(
            title = stringResource(R.string.product_form_active_title),
            body = stringResource(R.string.product_form_active_body),
            checked = uiState.isActive,
            onCheckedChange = { onEvent(ProductFormEvent.ActiveChanged(it)) },
            modifier = Modifier.testTag(ProductFormTags.ACTIVE),
        )
    }
}

private val VariantRowGlass = Color.White.copy(alpha = 0.6f)
