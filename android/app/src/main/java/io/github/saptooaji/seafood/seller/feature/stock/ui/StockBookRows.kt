package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Primary50
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Primary800
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import java.time.ZoneId

/** One day's movements under its heading, in one glass card. */
@Composable
internal fun DayGroup(
    heading: UiText?,
    movements: List<StockMovement>,
    zone: ZoneId,
) {
    Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
        if (heading != null) {
            Text(
                text = heading.asString(),
                style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold, fontSize = 13.sp),
                color = EmbunColors.Muted,
                modifier = Modifier.padding(horizontal = 4.dp).semantics { heading() },
            )
        }
        GlassCard(Modifier.fillMaxWidth(), contentPadding = PaddingValues(horizontal = 14.dp, vertical = 0.dp), spacing = 0.dp) {
            movements.forEachIndexed { index, movement ->
                if (index > 0) HorizontalDivider(color = EmbunColors.Line)
                MovementRow(movement, zone)
            }
        }
    }
}

/**
 * Sign mark, type, product and variant, details, and the change with the
 * stock after. A note is plain text, never markup.
 */
@Composable
private fun MovementRow(
    movement: StockMovement,
    zone: ZoneId,
) {
    val adds = movement.qty.signum() > 0
    val (markBackground, markColor) =
        when {
            movement.type == StockMovementType.Shrinkage -> EmbunColors.WarnBackground to EmbunColors.WarnContent
            adds -> Primary50.copy(alpha = 0.85f) to Primary800
            else -> Color.White.copy(alpha = 0.5f) to MaterialTheme.colorScheme.onSurface
        }
    Row(
        Modifier
            .fillMaxWidth()
            .padding(vertical = 14.dp)
            .semantics(mergeDescendants = true) {}
            .testTag(StockBookTags.row(movement.id)),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
    ) {
        Box(Modifier.size(40.dp).background(markBackground, RoundedCornerShape(12.dp)), contentAlignment = Alignment.Center) {
            Text(text = if (adds) "+" else "−", color = markColor, fontSize = 20.sp, fontWeight = FontWeight.ExtraBold)
        }
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = movement.typeLabel, style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
            Text(
                text = movement.productName + ", " + movement.variantName,
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
            )
            Text(
                text = movementDetails(movement, zone),
                style = MaterialTheme.typography.bodySmall.copy(fontSize = 12.sp),
                color = EmbunColors.Muted,
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(
                text = signedQuantity(movement.qty, movement.unitLabel),
                fontWeight = FontWeight.Bold,
                fontSize = 16.sp,
                color = if (movement.type == StockMovementType.Shrinkage) EmbunColors.WarnContent else markColor,
            )
            Text(text = stockAfter(movement).asString(), fontSize = 12.sp, color = EmbunColors.Muted)
        }
    }
}

/** Under the last line: loading the next page, or its failure with a retry. */
@Composable
internal fun MoreFooter(
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
        when {
            uiState.isLoadingMore -> CircularProgressIndicator(Modifier.size(28.dp), color = MaterialTheme.colorScheme.primary)
            uiState.loadMoreError != null ->
                TextButton(onClick = { onEvent(StockBookEvent.RetryLoadMore) }, modifier = Modifier.testTag(StockBookTags.RETRY_MORE)) {
                    Text(text = uiState.loadMoreError.asString(), fontWeight = FontWeight.Bold)
                }
        }
    }
}

@Composable
internal fun LoadingBook() {
    Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
    }
}

@Composable
internal fun BookFailed(
    error: UiText,
    onEvent: (StockBookEvent) -> Unit,
) {
    Column(Modifier.verticalScroll(rememberScrollState()).padding(horizontal = 16.dp)) {
        if (error == UiText.Resource(R.string.error_network)) {
            OfflineContent(
                onRetry = { onEvent(StockBookEvent.Retry) },
                modifier = Modifier.padding(top = 24.dp),
                retryModifier = Modifier.testTag(StockBookTags.RETRY),
            )
        } else {
            GlassCard(Modifier.fillMaxWidth()) {
                Text(text = error.asString(), style = MaterialTheme.typography.bodyLarge, color = EmbunColors.DangerContent)
                PrimaryPillButton(
                    text = stringResource(R.string.product_list_retry),
                    onClick = { onEvent(StockBookEvent.Retry) },
                    modifier = Modifier.testTag(StockBookTags.RETRY),
                )
            }
        }
    }
}

/** The board VD-Buku-Stok--saringan-tanpa-hasil, or an empty book when nothing filters. */
@Composable
internal fun EmptyBook(
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    val filtered = !uiState.filter.isEmpty
    Column(Modifier.verticalScroll(rememberScrollState()).padding(horizontal = 16.dp)) {
        GlassCard(Modifier.fillMaxWidth()) {
            Column(
                Modifier.fillMaxWidth().padding(vertical = 16.dp),
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(12.dp),
            ) {
                Text(
                    text = stringResource(if (filtered) R.string.stock_book_empty_filtered else R.string.stock_book_empty),
                    style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 21.sp),
                    textAlign = TextAlign.Center,
                )
                Text(
                    text = stringResource(if (filtered) R.string.stock_book_empty_filtered_body else R.string.stock_book_empty_body),
                    style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp),
                    color = EmbunColors.Muted,
                    textAlign = TextAlign.Center,
                )
                if (filtered) {
                    PrimaryPillButton(
                        text = stringResource(R.string.stock_book_clear_filter),
                        onClick = { onEvent(StockBookEvent.ClearFilter) },
                        modifier = Modifier.testTag(StockBookTags.CLEAR_FILTER),
                    )
                }
            }
        }
    }
}
