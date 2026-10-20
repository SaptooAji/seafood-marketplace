package io.github.saptooaji.seafood.seller.feature.product.ui

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
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.error
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.semantics.stateDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Ink
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset
import java.time.format.DateTimeParseException

/**
 * One attribute of the picked category, drawn by its type: a field for text
 * and numbers (with the unit after it), pills for a choice, a date picker
 * for a date. A required one has a star after its name.
 */
@Composable
fun AttributeField(
    attribute: CategoryAttribute,
    value: String,
    error: String?,
    onValueChange: (String) -> Unit,
    modifier: Modifier = Modifier,
) {
    val label = if (attribute.isRequired) stringResource(R.string.product_form_required_label, attribute.name) else attribute.name
    Column(modifier) {
        when (attribute.type) {
            AttributeType.Text, AttributeType.Number ->
                GlassTextField(
                    value = value,
                    onValueChange = onValueChange,
                    label = label,
                    error = error,
                    trailing = attribute.unit?.let { { FieldAffix(it) } },
                    keyboardOptions =
                        KeyboardOptions(
                            keyboardType = if (attribute.type == AttributeType.Number) KeyboardType.Decimal else KeyboardType.Text,
                            imeAction = ImeAction.Next,
                        ),
                    modifier = Modifier.testTag(ProductFormTags.attribute(attribute.id)),
                )
            AttributeType.Select -> SelectAttribute(attribute, label, value, error, onValueChange)
            AttributeType.Date -> DateAttribute(attribute, label, value, error, onValueChange)
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun SelectAttribute(
    attribute: CategoryAttribute,
    label: String,
    value: String,
    error: String?,
    onValueChange: (String) -> Unit,
) {
    Column(Modifier.testTag(ProductFormTags.attribute(attribute.id)), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = label, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        FlowRow(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            attribute.options.forEach { option ->
                ChoicePill(
                    text = option,
                    selected = option == value,
                    // Tapping the picked one again clears it; an optional choice can be left empty.
                    onClick = { onValueChange(if (option == value) "" else option) },
                    modifier = Modifier.testTag(ProductFormTags.attributeOption(attribute.id, option)),
                )
            }
        }
        if (error != null) FieldError(error)
    }
}

/** A date kept as the server reads it, `2026-10-20`, and shown the same way. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun DateAttribute(
    attribute: CategoryAttribute,
    label: String,
    value: String,
    error: String?,
    onValueChange: (String) -> Unit,
) {
    var picking by rememberSaveable { mutableStateOf(false) }
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = label, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp)
                    .background(EmbunColors.Field, OptionShape)
                    .border(1.5.dp, if (error != null) EmbunColors.DangerContent else EmbunColors.FieldEdge, OptionShape)
                    .clickable(role = Role.Button, onClickLabel = stringResource(R.string.product_form_pick_date)) { picking = true }
                    .semantics { if (error != null) error(error) }
                    .testTag(ProductFormTags.attribute(attribute.id))
                    .padding(horizontal = 18.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Text(
                text = value.ifEmpty { stringResource(R.string.product_form_pick_date) },
                style = MaterialTheme.typography.bodyLarge,
                color = if (value.isEmpty()) EmbunColors.Muted else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            Icon(
                painter = painterResource(R.drawable.ic_calendar),
                contentDescription = null,
                tint = EmbunColors.Muted,
                modifier = Modifier.size(20.dp),
            )
        }
        if (error != null) FieldError(error)
    }

    if (picking) {
        val state = rememberDatePickerState(initialSelectedDateMillis = value.toEpochMillis())
        DatePickerDialog(
            onDismissRequest = { picking = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        state.selectedDateMillis?.let { onValueChange(it.toIsoDate()) }
                        picking = false
                    },
                ) { Text(stringResource(R.string.product_form_date_ok)) }
            },
            dismissButton = {
                TextButton(onClick = { picking = false }) { Text(stringResource(R.string.product_form_date_cancel)) }
            },
        ) {
            DatePicker(state = state)
        }
    }
}

// The date picker counts in UTC midnights.
private fun String.toEpochMillis(): Long? =
    try {
        LocalDate
            .parse(this)
            .atStartOfDay(ZoneOffset.UTC)
            .toInstant()
            .toEpochMilli()
    } catch (_: DateTimeParseException) {
        null
    }

private fun Long.toIsoDate(): String =
    Instant
        .ofEpochMilli(this)
        .atZone(ZoneOffset.UTC)
        .toLocalDate()
        .toString()

/**
 * The category picker (board VD-Pilih-Kategori). A top category opens to
 * show what is under it; only a category without children can be picked,
 * since products go in the most specific one.
 */
@Composable
fun BoxScope.CategorySheet(
    categories: List<Category>,
    selectedId: Long?,
    onPick: (Long) -> Unit,
    onDismiss: () -> Unit,
) {
    var opened by rememberSaveable { mutableStateOf<Long?>(null) }
    EmbunSheet(onDismiss = onDismiss, modifier = Modifier.heightIn(max = SHEET_MAX_HEIGHT.dp)) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
            SheetHeading(
                title = stringResource(R.string.product_category_title),
                body = stringResource(R.string.product_category_subtitle),
                modifier = Modifier.weight(1f),
            )
            Surface(
                onClick = onDismiss,
                shape = CircleShape,
                color = EmbunColors.GlassStrong,
                modifier = Modifier.size(44.dp).testTag(ProductFormTags.CATEGORY_CLOSE),
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        painter = painterResource(R.drawable.ic_close),
                        contentDescription = stringResource(R.string.product_category_close),
                        modifier = Modifier.size(22.dp),
                    )
                }
            }
        }
        Column(Modifier.verticalScroll(rememberScrollState())) {
            categories.forEach { category ->
                CategoryRow(
                    category = category,
                    expanded = opened == category.id,
                    selectedId = selectedId,
                    onToggle = { opened = if (opened == category.id) null else category.id },
                    onPick = onPick,
                )
            }
        }
    }
}

@OptIn(ExperimentalLayoutApi::class)
@Composable
private fun CategoryRow(
    category: Category,
    expanded: Boolean,
    selectedId: Long?,
    onToggle: () -> Unit,
    onPick: (Long) -> Unit,
) {
    val leaves = category.leaves()
    val state = stringResource(if (expanded) R.string.product_category_expanded else R.string.product_category_collapsed)
    Column(Modifier.fillMaxWidth()) {
        Box(Modifier.fillMaxWidth().heightIn(min = 1.dp).background(EmbunColors.Line))
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 56.dp)
                    .clickable(role = Role.Button) { if (category.isLeaf) onPick(category.id) else onToggle() }
                    .semantics { if (!category.isLeaf) stateDescription = state }
                    .testTag(ProductFormTags.categoryRow(category.id))
                    .padding(vertical = 8.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Column(Modifier.weight(1f)) {
                Text(text = category.name, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold))
                if (!category.isLeaf) {
                    Text(
                        text = leaves.joinToString { it.name },
                        style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                        color = EmbunColors.Muted,
                    )
                }
            }
            if (!category.isLeaf) {
                Icon(
                    painter = painterResource(R.drawable.ic_chevron_down),
                    contentDescription = null,
                    tint = EmbunColors.Muted,
                    modifier = Modifier.size(20.dp),
                )
            }
        }
        if (expanded) {
            FlowRow(
                modifier = Modifier.fillMaxWidth().padding(bottom = 14.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
            ) {
                leaves.forEach { leaf ->
                    val selected = leaf.id == selectedId
                    Text(
                        text = leaf.name,
                        style = MaterialTheme.typography.labelLarge.copy(fontSize = 14.sp),
                        color = if (selected) Color.White else Ink,
                        modifier =
                            Modifier
                                .background(if (selected) Ink else LeafGlass, OptionShape)
                                .clickable(role = Role.Button) { onPick(leaf.id) }
                                .testTag(ProductFormTags.categoryRow(leaf.id))
                                .padding(horizontal = 14.dp, vertical = 12.dp),
                    )
                }
            }
        }
    }
}

/** Every category under this one that a product can go in, however deep. */
private fun Category.leaves(): List<Category> = children.flatMap { if (it.isLeaf) listOf(it) else it.leaves() }

private const val SHEET_MAX_HEIGHT = 640
private val LeafGlass = Color.White.copy(alpha = 0.5f)
