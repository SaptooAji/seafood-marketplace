package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.animation.core.FastOutSlowInEasing
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.StartOffset
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.DescriptionSuggestion

/** The AI description sheet (#240, boards VD-Saran-AI). */
sealed interface SuggestionSheet {
    /** Waiting for the AI, which can take a while. */
    data object Loading : SuggestionSheet

    /**
     * The suggestion, shown only. [confirmReplace] is set after the first
     * "Pakai saran" over a written description; the next tap replaces it.
     */
    data class Ready(
        val suggestion: DescriptionSuggestion,
        val confirmReplace: Boolean = false,
    ) : SuggestionSheet

    /**
     * Why there is no suggestion. [canRetry] for a connection or server
     * trouble; a used-up limit or an AI that is off offers to write it oneself.
     */
    data class Failed(
        val message: UiText,
        val canRetry: Boolean,
    ) : SuggestionSheet
}

private const val SECONDS_PER_MINUTE = 60L

/** A wait of an hour or more is the daily quota, not the per-minute limit. */
private const val QUOTA_WAIT_SECONDS = 3600L

/**
 * What the sheet says about a failed suggestion. A short 429 wait is told in
 * the app's words from Retry-After, since the general API limit answers in
 * English. A wait of an hour or more comes only from the app's own daily
 * limits, the store's or the platform's, so their Indonesian message says
 * which.
 */
internal fun ApiResult<*>.suggestionFailure(): SuggestionSheet.Failed =
    when (this) {
        is ApiResult.TooManyRequests -> SuggestionSheet.Failed(waitMessage(retryAfterSeconds, message), canRetry = false)
        is ApiResult.ServiceUnavailable ->
            SuggestionSheet.Failed(message?.let(UiText::Raw) ?: UiText.Resource(R.string.product_suggestion_unavailable), canRetry = false)
        is ApiResult.Invalid ->
            SuggestionSheet.Failed(
                message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server),
                canRetry = false,
            )
        is ApiResult.Forbidden ->
            SuggestionSheet.Failed(
                message?.let(UiText::Raw) ?: UiText.Resource(R.string.error_server),
                canRetry = false,
            )
        ApiResult.NetworkError -> SuggestionSheet.Failed(UiText.Resource(R.string.error_network), canRetry = true)
        else -> SuggestionSheet.Failed(UiText.Resource(R.string.error_server), canRetry = true)
    }

private fun waitMessage(
    seconds: Long?,
    serverMessage: String?,
): UiText =
    when {
        seconds == null -> UiText.Resource(R.string.product_suggestion_wait)
        seconds >= QUOTA_WAIT_SECONDS -> serverMessage?.let(UiText::Raw) ?: UiText.Resource(R.string.product_suggestion_quota_used)
        else -> {
            val minutes = ((seconds + SECONDS_PER_MINUTE - 1) / SECONDS_PER_MINUTE).coerceAtLeast(1)
            UiText.Resource(R.string.product_suggestion_too_often, listOf(minutes))
        }
    }

/**
 * The button under the description (board VD-Produk-Form), faded with a hint
 * until the form has what the AI needs (VD-Produk-Form--saran-ai-belum-bisa).
 */
@Composable
fun SuggestButton(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
) {
    val enabled = uiState.canSuggestDescription
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Surface(
            onClick = { onEvent(ProductFormEvent.SuggestDescription) },
            enabled = enabled,
            shape = PillShape,
            color = EmbunColors.HaloCalm.copy(alpha = BUTTON_ALPHA),
            contentColor = MaterialTheme.colorScheme.onSurface,
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 44.dp)
                    .alpha(if (enabled) 1f else DISABLED_ALPHA)
                    .testTag(ProductFormTags.SUGGEST),
        ) {
            Row(
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 11.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Icon(painter = painterResource(R.drawable.ic_sparkle), contentDescription = null, modifier = Modifier.size(20.dp))
                Text(text = stringResource(R.string.product_suggest), style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp))
            }
        }
        if (uiState.lacksWhatTheAiNeeds) {
            Text(
                text = stringResource(R.string.product_suggest_hint),
                style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
                modifier = Modifier.padding(start = 4.dp),
            )
        }
    }
}

/** The sheet for each [SuggestionSheet] state. */
@Composable
fun BoxScope.SuggestionSheetContent(
    sheet: SuggestionSheet,
    onEvent: (ProductFormEvent) -> Unit,
) {
    val dismiss = { onEvent(ProductFormEvent.DismissSuggestion) }
    EmbunSheet(onDismiss = dismiss) {
        Column(Modifier.verticalScroll(rememberScrollState()), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            SuggestionHeading(dismiss)
            when (sheet) {
                SuggestionSheet.Loading -> SuggestionLoading()
                is SuggestionSheet.Ready -> SuggestionResult(sheet, onEvent)
                is SuggestionSheet.Failed -> SuggestionFailed(sheet, onEvent)
            }
        }
    }
}

@Composable
private fun SuggestionHeading(onClose: () -> Unit) {
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp), verticalAlignment = Alignment.CenterVertically) {
        SheetHeading(
            title = stringResource(R.string.product_suggestion_title),
            body = stringResource(R.string.product_suggestion_subtitle),
            modifier = Modifier.weight(1f),
        )
        Surface(onClick = onClose, shape = CircleShape, color = EmbunColors.GlassStrong, modifier = Modifier.size(44.dp)) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    painter = painterResource(R.drawable.ic_close),
                    contentDescription = stringResource(R.string.product_suggestion_close),
                    modifier = Modifier.size(22.dp),
                )
            }
        }
    }
}

/**
 * What the AI reads, the wait, and placeholder lines that pulse one after
 * another (VD-Saran-AI--memuat). With animations off in the system settings,
 * Compose shows the lines still.
 */
@Composable
private fun SuggestionLoading() {
    val pulse = rememberInfiniteTransition(label = "suggestion_loading")
    Column(
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.padding(bottom = 12.dp).semantics { liveRegion = LiveRegionMode.Polite },
    ) {
        Text(
            text = stringResource(R.string.product_suggestion_loading),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp),
            color = EmbunColors.Muted,
        )
        LOADING_LINES.forEachIndexed { index, width ->
            val alpha by pulse.animateFloat(
                initialValue = PULSE_MIN_ALPHA,
                targetValue = 1f,
                animationSpec =
                    infiniteRepeatable(
                        animation = tween(PULSE_HALF_MILLIS, easing = FastOutSlowInEasing),
                        repeatMode = RepeatMode.Reverse,
                        initialStartOffset = StartOffset(index * PULSE_STAGGER_MILLIS),
                    ),
                label = "suggestion_line_$index",
            )
            Box(
                Modifier
                    .fillMaxWidth(width)
                    .heightIn(min = 14.dp)
                    .graphicsLayer { this.alpha = alpha }
                    .background(EmbunColors.GlassStrong, PillShape),
            )
        }
    }
}

@Composable
private fun SuggestionResult(
    sheet: SuggestionSheet.Ready,
    onEvent: (ProductFormEvent) -> Unit,
) {
    SuggestedText(stringResource(R.string.product_suggestion_description_label), sheet.suggestion.description)
    if (sheet.suggestion.seoDescription.isNotBlank()) {
        SuggestedText(stringResource(R.string.product_suggestion_seo_label), sheet.suggestion.seoDescription)
    }
    Text(
        text = stringResource(R.string.product_suggestion_note),
        style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
        color = EmbunColors.Muted,
    )
    if (sheet.confirmReplace) GlassBanner(text = stringResource(R.string.product_suggestion_replace_warning), tone = BannerTone.Warn)
    Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        GlassPillButton(
            text = stringResource(R.string.product_suggestion_cancel),
            onClick = { onEvent(ProductFormEvent.DismissSuggestion) },
            modifier = Modifier.weight(1f).testTag(ProductFormTags.SUGGESTION_CANCEL),
        )
        PrimaryPillButton(
            text = stringResource(if (sheet.confirmReplace) R.string.product_suggestion_replace else R.string.product_suggestion_use),
            onClick = { onEvent(ProductFormEvent.UseSuggestion) },
            modifier = Modifier.weight(2f).testTag(ProductFormTags.SUGGESTION_USE),
        )
    }
}

/** A suggested text as plain text: the app never renders HTML (AI-3). */
@Composable
private fun SuggestedText(
    label: String,
    text: String,
) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = label,
            style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold),
            color = EmbunColors.Muted,
        )
        Text(
            text = text,
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp),
            modifier =
                Modifier
                    .fillMaxWidth()
                    .background(EmbunColors.GlassStrong, OptionShape)
                    .padding(horizontal = 16.dp, vertical = 14.dp),
        )
    }
}

@Composable
private fun SuggestionFailed(
    sheet: SuggestionSheet.Failed,
    onEvent: (ProductFormEvent) -> Unit,
) {
    GlassBanner(text = sheet.message.asString(), tone = BannerTone.Warn)
    if (sheet.canRetry) {
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            GlassPillButton(
                text = stringResource(R.string.product_suggestion_close),
                onClick = { onEvent(ProductFormEvent.DismissSuggestion) },
                modifier = Modifier.weight(1f).testTag(ProductFormTags.SUGGESTION_CLOSE),
            )
            PrimaryPillButton(
                text = stringResource(R.string.product_suggestion_retry),
                onClick = { onEvent(ProductFormEvent.SuggestDescription) },
                modifier = Modifier.weight(2f).testTag(ProductFormTags.SUGGESTION_RETRY),
            )
        }
    } else {
        GlassPillButton(
            text = stringResource(R.string.product_suggestion_write_own),
            onClick = { onEvent(ProductFormEvent.DismissSuggestion) },
            modifier = Modifier.testTag(ProductFormTags.SUGGESTION_CLOSE),
        )
    }
}

private const val BUTTON_ALPHA = 0.85f
private const val DISABLED_ALPHA = 0.5f

// The board's pulse: 1.4 s from faint to full and back, each line 0.15 s after the one above.
private const val PULSE_HALF_MILLIS = 700
private const val PULSE_STAGGER_MILLIS = 150
private const val PULSE_MIN_ALPHA = 0.45f

/** The widths of the placeholder lines while waiting, as on the board. */
private val LOADING_LINES = listOf(1f, 0.92f, 0.76f, 0.84f)
