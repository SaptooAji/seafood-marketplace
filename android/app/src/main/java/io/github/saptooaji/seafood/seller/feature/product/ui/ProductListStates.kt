package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.util.UiText

@Composable
internal fun ListFooter(
    uiState: ProductListUiState,
    onEvent: (ProductListEvent) -> Unit,
) {
    val footerModifier = Modifier.fillMaxWidth().padding(top = 4.dp)
    when {
        uiState.loadMoreError != null ->
            Row(
                modifier =
                    footerModifier
                        .background(EmbunColors.DangerBackground, RoundedCornerShape(18.dp))
                        .border(1.dp, EmbunColors.GlassEdge, RoundedCornerShape(18.dp))
                        .padding(horizontal = 14.dp, vertical = 12.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                Text(
                    text = uiState.loadMoreError.asString(),
                    style = MaterialTheme.typography.bodyMedium,
                    color = EmbunColors.DangerContent,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = stringResource(R.string.product_list_retry),
                    style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.ExtraBold),
                    color = EmbunColors.DangerContent,
                    modifier =
                        Modifier
                            .clip(PillShape)
                            .clickable(role = Role.Button) { onEvent(ProductListEvent.RetryLoadMore) }
                            .testTag(ProductListTags.RETRY_MORE)
                            .padding(8.dp),
                )
            }
        uiState.isLoadingMore ->
            Row(
                footerModifier,
                horizontalArrangement = Arrangement.spacedBy(10.dp, Alignment.CenterHorizontally),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                CircularProgressIndicator(strokeWidth = 2.5.dp, modifier = Modifier.size(18.dp))
                Text(
                    text = stringResource(R.string.product_list_loading_more),
                    style = MaterialTheme.typography.bodyMedium,
                    color = EmbunColors.Muted,
                )
            }
        uiState.endReached ->
            Text(
                text = pluralStringResource(R.plurals.product_list_all_shown, uiState.products.size, uiState.products.size),
                style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
                textAlign = TextAlign.Center,
                modifier = footerModifier,
            )
    }
}

@Composable
internal fun LoadingRows() {
    val transition = rememberInfiniteTransition(label = "loading rows")
    val pulse by transition.animateFloat(
        initialValue = PULSE_LOW,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(tween(durationMillis = 800), RepeatMode.Reverse),
        label = "pulse",
    )
    val loading = stringResource(R.string.product_list_loading)
    Column(
        modifier = Modifier.fillMaxSize().padding(horizontal = 16.dp).semantics { contentDescription = loading },
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        repeat(SKELETON_ROWS) {
            Row(
                modifier =
                    Modifier
                        .fillMaxWidth()
                        .alpha(pulse)
                        .background(EmbunColors.Glass, RowShape)
                        .border(1.dp, EmbunColors.GlassEdge, RowShape)
                        .padding(10.dp),
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(14.dp),
            ) {
                Box(Modifier.size(76.dp).background(EmbunColors.Line, CircleShape))
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Box(Modifier.width(150.dp).height(14.dp).background(EmbunColors.Line, PillShape))
                    Box(Modifier.width(100.dp).height(11.dp).background(EmbunColors.Line, PillShape))
                    Box(Modifier.width(120.dp).height(14.dp).background(EmbunColors.Line, PillShape))
                }
            }
        }
    }
}

/** Empty states scroll too, so pull to refresh still works on them. */
@Composable
internal fun Scrollable(content: @Composable () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(start = 16.dp, end = 16.dp, bottom = 110.dp)) {
        content()
    }
}

@Composable
private fun EmptyPanel(
    icon: @Composable () -> Unit,
    title: String,
    body: String,
    action: @Composable () -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        Column(
            modifier = Modifier.fillMaxWidth().padding(vertical = 16.dp, horizontal = 4.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Box(Modifier.size(72.dp).background(EmbunColors.GlassButton, CircleShape), contentAlignment = Alignment.Center) { icon() }
            Text(
                text = title,
                style = MaterialTheme.typography.titleLarge.copy(fontWeight = FontWeight.Bold, fontSize = 21.sp),
                textAlign = TextAlign.Center,
                modifier = Modifier.semantics { heading() },
            )
            Text(
                text = body,
                style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp),
                color = EmbunColors.Muted,
                textAlign = TextAlign.Center,
            )
            action()
        }
    }
}

@Composable
internal fun NoProducts(onAddProduct: () -> Unit) {
    EmptyPanel(
        icon = { Image(painterResource(R.drawable.ic_logo_mark), contentDescription = null, modifier = Modifier.size(44.dp)) },
        title = stringResource(R.string.product_list_empty_title),
        body = stringResource(R.string.product_list_empty_body),
    ) {
        PrimaryPillButton(
            text = stringResource(R.string.product_list_add_first),
            onClick = onAddProduct,
            modifier = Modifier.testTag(ProductListTags.ADD_FIRST),
        )
    }
}

@Composable
internal fun NoResults(
    uiState: ProductListUiState,
    onEvent: (ProductListEvent) -> Unit,
) {
    val query = uiState.query.trim()
    EmptyPanel(
        icon = {
            Icon(
                painterResource(R.drawable.ic_search),
                contentDescription = null,
                tint = EmbunColors.Muted,
                modifier = Modifier.size(30.dp),
            )
        },
        title =
            if (query.isNotEmpty()) {
                stringResource(R.string.product_list_no_results, query)
            } else {
                stringResource(R.string.product_list_no_results_filter)
            },
        body = stringResource(R.string.product_list_no_results_body),
    ) {
        if (query.isNotEmpty()) {
            GlassPillButton(
                text = stringResource(R.string.product_search_clear),
                onClick = { onEvent(ProductListEvent.QueryChanged("")) },
                modifier = Modifier.testTag(ProductListTags.CLEAR_SEARCH_RESULTS),
            )
        }
    }
}

@Composable
internal fun FirstPageError(
    error: UiText?,
    onEvent: (ProductListEvent) -> Unit,
) {
    if (error == UiText.Resource(R.string.error_network)) {
        OfflineContent(
            onRetry = { onEvent(ProductListEvent.Retry) },
            modifier = Modifier.padding(top = 24.dp),
            retryModifier = Modifier.testTag(ProductListTags.RETRY),
        )
    } else {
        GlassCard(Modifier.fillMaxWidth()) {
            Text(text = error?.asString().orEmpty(), style = MaterialTheme.typography.bodyLarge, color = EmbunColors.DangerContent)
            PrimaryPillButton(
                text = stringResource(R.string.product_list_retry),
                onClick = { onEvent(ProductListEvent.Retry) },
                modifier = Modifier.testTag(ProductListTags.RETRY),
            )
        }
    }
}

private const val PULSE_LOW = 0.45f
private const val SKELETON_ROWS = 4
