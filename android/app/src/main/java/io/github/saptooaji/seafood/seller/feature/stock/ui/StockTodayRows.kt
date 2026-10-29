package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Primary100
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant

/** The ring of variants counted today, out of all, as in the board. */
@Composable
internal fun Summary(
    uiState: StockTodayUiState,
    onOpenBook: () -> Unit,
) {
    val left = uiState.total - uiState.checkedToday
    GlassCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
            val primary = MaterialTheme.colorScheme.primary
            val share = if (uiState.total == 0) 0f else uiState.checkedToday.toFloat() / uiState.total
            Box(Modifier.size(RING), contentAlignment = Alignment.Center) {
                Canvas(Modifier.size(RING)) {
                    val stroke = RING_STROKE.toPx()
                    val inset = Offset(stroke / 2, stroke / 2)
                    val arc = Size(size.width - stroke, size.height - stroke)
                    drawArc(Color.White.copy(alpha = 0.7f), 0f, FULL_TURN, false, inset, arc, style = Stroke(stroke))
                    drawArc(primary, START_AT_TOP, FULL_TURN * share, false, inset, arc, style = Stroke(stroke))
                }
                Text(text = "${uiState.checkedToday}/${uiState.total}", fontWeight = FontWeight.ExtraBold, fontSize = 20.sp)
            }
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text(
                    text =
                        if (left == 0) {
                            stringResource(R.string.stock_summary_done)
                        } else {
                            stringResource(R.string.stock_summary_left, left)
                        },
                    style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 18.sp),
                )
                Text(
                    text = stringResource(R.string.stock_open_book),
                    style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.Bold, fontSize = 14.sp),
                    color = MaterialTheme.colorScheme.primary,
                    modifier =
                        Modifier
                            .clickable(role = Role.Button, onClick = onOpenBook)
                            .padding(vertical = 4.dp)
                            .testTag(StockTodayTags.OPEN_BOOK),
                )
            }
        }
    }
}

/** A product's glass card with one row per variant. */
@Composable
internal fun ProductCard(
    product: StockProduct,
    uiState: StockTodayUiState,
    onEvent: (StockTodayEvent) -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth(), contentPadding = PaddingValues(14.dp), spacing = 6.dp) {
        Text(text = product.name, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp))
        product.variants.forEach { variant ->
            HorizontalDivider(color = EmbunColors.Line)
            VariantRow(variant, uiState, onEvent)
        }
    }
}

@Composable
private fun VariantRow(
    variant: StockVariant,
    uiState: StockTodayUiState,
    onEvent: (StockTodayEvent) -> Unit,
) {
    val id = variant.id
    val unit = variant.unitLabel.lowercase()
    val changed = uiState.isChanged(id)
    val stale = variant.isStale || variant.confirmedAt == null
    val note =
        if (changed) {
            UiText.Resource(R.string.stock_changed_from, listOf(fieldText(variant.stock), unit))
        } else {
            checkedNote(variant.confirmedAt, uiState.now)
        }
    Column(Modifier.padding(vertical = 6.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            Column(Modifier.weight(1f).testTag(StockTodayTags.row(id)).semantics(mergeDescendants = true) {}) {
                Text(text = variant.name, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
                Text(
                    text = note.asString(),
                    style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp),
                    color = if (changed || stale) EmbunColors.WarnContent else EmbunColors.Muted,
                )
                if (stale && !changed) StaleBadge()
            }
            if (changed) {
                TextButton(onClick = { onEvent(StockTodayEvent.Revert(id)) }, modifier = Modifier.testTag(StockTodayTags.revert(id))) {
                    Text(
                        text = stringResource(R.string.stock_revert, fieldText(variant.stock)),
                        fontWeight = FontWeight.Bold,
                        fontSize = 13.sp,
                    )
                }
            }
        }
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            StepButton("−", stringResource(R.string.stock_decrease, variant.name), uiState.canDecrease(id), StockTodayTags.decrease(id)) {
                onEvent(StockTodayEvent.Decrease(id))
            }
            CountField(uiState, variant, unit, onEvent, Modifier.weight(1f))
            StepButton("+", stringResource(R.string.stock_increase, variant.name), true, StockTodayTags.increase(id)) {
                onEvent(StockTodayEvent.Increase(id))
            }
        }
        uiState.error(id)?.let {
            Text(text = it.asString(), style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp), color = EmbunColors.DangerContent)
        }
    }
}

@Composable
private fun StaleBadge() {
    Text(
        text = stringResource(R.string.stock_stale_badge),
        style = MaterialTheme.typography.labelSmall.copy(fontWeight = FontWeight.Bold, fontSize = 12.sp),
        color = EmbunColors.WarnContent,
        modifier =
            Modifier
                .padding(
                    top = 4.dp,
                ).background(EmbunColors.WarnBackground, PillShape)
                .padding(horizontal = 10.dp, vertical = 3.dp),
    )
}

@Composable
private fun StepButton(
    symbol: String,
    label: String,
    enabled: Boolean,
    tag: String,
    onClick: () -> Unit,
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = if (enabled) EmbunColors.GlassButton else EmbunColors.Disabled,
        contentColor = if (enabled) MaterialTheme.colorScheme.onSurface else EmbunColors.Muted,
        modifier =
            Modifier
                .size(STEP)
                .border(1.dp, EmbunColors.GlassEdge, PillShape)
                .semantics { contentDescription = label }
                .testTag(tag),
    ) {
        Box(contentAlignment = Alignment.Center) { Text(text = symbol, fontSize = 24.sp) }
    }
}

/** The count, typed straight in; green when it differs from the server's, red edged when refused. */
@Composable
private fun CountField(
    uiState: StockTodayUiState,
    variant: StockVariant,
    unit: String,
    onEvent: (StockTodayEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    val id = variant.id
    val refused = uiState.error(id) != null
    val label = stringResource(R.string.stock_field, variant.name)
    Row(
        modifier =
            modifier
                .height(STEP)
                .background(if (uiState.isChanged(id)) Primary100.copy(alpha = 0.9f) else Color.White.copy(alpha = 0.6f), PillShape)
                .then(if (refused) Modifier.border(2.dp, EmbunColors.DangerContent, PillShape) else Modifier)
                .padding(horizontal = 16.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
    ) {
        BasicTextField(
            value = uiState.text(id),
            onValueChange = { onEvent(StockTodayEvent.Typed(id, it)) },
            singleLine = true,
            textStyle =
                TextStyle(
                    fontSize = 22.sp,
                    fontWeight = FontWeight.Bold,
                    textAlign = TextAlign.End,
                    color = MaterialTheme.colorScheme.onSurface,
                ),
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal, imeAction = ImeAction.Done),
            modifier = Modifier.weight(1f).semantics { contentDescription = label }.testTag(StockTodayTags.field(id)),
        )
        Text(text = unit, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp), color = EmbunColors.Muted)
    }
}

/** The board VD-Stok-Hari-Ini--belum-ada-ukuran: nothing to count until a product has an active variant. */
@Composable
internal fun NoVariants(onOpenProducts: () -> Unit) {
    GlassCard(Modifier.fillMaxWidth()) {
        Column(
            Modifier.fillMaxWidth().padding(vertical = 16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Text(
                text = stringResource(R.string.stock_empty_title),
                style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 21.sp),
                textAlign = TextAlign.Center,
            )
            Text(
                text = stringResource(R.string.stock_empty_body),
                style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp),
                color = EmbunColors.Muted,
                textAlign = TextAlign.Center,
            )
            PrimaryPillButton(
                text = stringResource(R.string.stock_open_products),
                onClick = onOpenProducts,
                modifier = Modifier.testTag(StockTodayTags.OPEN_PRODUCTS),
            )
        }
    }
}

/** No internet: the shared offline panel; any other failure says so with a retry. */
@Composable
internal fun LoadFailed(
    error: UiText,
    onEvent: (StockTodayEvent) -> Unit,
) {
    if (error == UiText.Resource(R.string.error_network)) {
        OfflineContent(
            onRetry = { onEvent(StockTodayEvent.Retry) },
            modifier = Modifier.padding(top = 24.dp),
            retryModifier = Modifier.testTag(StockTodayTags.RETRY),
        )
    } else {
        GlassCard(Modifier.fillMaxWidth()) {
            Text(text = error.asString(), style = MaterialTheme.typography.bodyLarge, color = EmbunColors.DangerContent)
            PrimaryPillButton(
                text = stringResource(R.string.product_list_retry),
                onClick = { onEvent(StockTodayEvent.Retry) },
                modifier = Modifier.testTag(StockTodayTags.RETRY),
            )
        }
    }
}

private val RING = 86.dp
private val RING_STROKE = 10.dp
private val STEP = 50.dp
private const val FULL_TURN = 360f
private const val START_AT_TOP = -90f
