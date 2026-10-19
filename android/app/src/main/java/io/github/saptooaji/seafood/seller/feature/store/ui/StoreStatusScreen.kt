package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.PathEffect
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusChip
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusHeading
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusOrb
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.TextPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus

/** The store status destination: wires [StoreStatusViewModel] to the stateless screen. */
@Composable
fun StoreStatusScreen(
    onMain: () -> Unit,
    onOpenStore: () -> Unit,
    onSignedOut: () -> Unit,
    onStartProducts: () -> Unit,
    viewModel: StoreStatusViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(uiState.destination) {
        when (uiState.destination) {
            StoreStatusDestination.Main -> onMain()
            StoreStatusDestination.OpenStore -> onOpenStore()
            StoreStatusDestination.Login -> onSignedOut()
            null -> Unit
        }
    }

    StoreStatusScreen(uiState = uiState, onEvent = viewModel::onEvent, onStartProducts = onStartProducts)
}

/** Test tags of the store status screen. */
object StoreStatusTags {
    const val START_PRODUCTS = "store_status_start_products"
    const val CHECK_AGAIN = "store_status_check_again"
    const val SIGN_OUT = "store_status_sign_out"
    const val RETRY = "store_status_retry"
}

/**
 * The store as the admin left it, as the board VD-Status-Toko draws it:
 * waiting (with the steps so far), rejected (with the admin's note), or
 * suspended. A pending store may already add products (AC-7 refuses only a
 * suspended one).
 */
@Composable
fun StoreStatusScreen(
    uiState: StoreStatusUiState,
    onEvent: (StoreStatusEvent) -> Unit,
    onStartProducts: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(
            modifier =
                Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .safeDrawingPadding()
                    .padding(start = 16.dp, end = 16.dp, top = 28.dp, bottom = 20.dp),
            verticalArrangement = Arrangement.spacedBy(18.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            val store = uiState.store
            when {
                store != null -> StoreStatusContent(store, uiState, onEvent, onStartProducts)
                uiState.offline ->
                    OfflineContent(onRetry = {
                        onEvent(StoreStatusEvent.CheckAgain)
                    }, retryModifier = Modifier.testTag(StoreStatusTags.RETRY))
                // Loading: the gradient alone, as the start screen shows it.
                else -> Unit
            }
            if (store != null || uiState.offline) {
                TextPillButton(
                    text = stringResource(if (uiState.isSigningOut) R.string.signing_out else R.string.sign_out),
                    onClick = { onEvent(StoreStatusEvent.SignOut) },
                    enabled = !uiState.isSigningOut,
                    modifier = Modifier.testTag(StoreStatusTags.SIGN_OUT),
                )
            }
        }
    }
}

@Composable
private fun StoreStatusContent(
    store: StoreStatusInfo,
    uiState: StoreStatusUiState,
    onEvent: (StoreStatusEvent) -> Unit,
    onStartProducts: () -> Unit,
) {
    val look = StatusLook.of(store.status)
    StatusOrb(icon = look.icon, label = look.badge?.let { stringResource(it) } ?: store.statusLabel, tone = look.tone)
    StatusHeading(
        title = look.title?.let { stringResource(it) } ?: stringResource(R.string.store_status_other_title, store.statusLabel),
        body = look.body?.let { stringResource(it) }.orEmpty(),
    )

    StoreCard(store, look.tone, uiState.serverMessage)

    if (store.status == StoreStatus.Pending) {
        PrimaryPillButton(
            text = stringResource(R.string.store_status_start_products),
            onClick = onStartProducts,
            modifier = Modifier.testTag(StoreStatusTags.START_PRODUCTS),
        )
    }
    GlassPillButton(
        text = stringResource(if (uiState.isChecking) R.string.store_status_checking else R.string.store_status_check_again),
        onClick = { onEvent(StoreStatusEvent.CheckAgain) },
        enabled = !uiState.isChecking && !uiState.isSigningOut,
        modifier = Modifier.testTag(StoreStatusTags.CHECK_AGAIN),
    )
    if (uiState.offline) {
        Text(text = stringResource(R.string.offline_body), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.WarnContent)
    }
}

/** The store's name and status, with what that status needs: the steps, the admin's note, or how to reach the admin. */
@Composable
private fun StoreCard(
    store: StoreStatusInfo,
    tone: StatusTone,
    serverMessage: String?,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(
                text = store.name,
                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp),
                color = MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            StatusChip(text = store.statusLabel, tone = tone)
        }
        when (store.status) {
            StoreStatus.Pending -> PendingSteps()
            StoreStatus.Rejected -> store.rejectionReason?.let { RejectionNote(it) }
            StoreStatus.Suspended ->
                Text(
                    text = serverMessage ?: stringResource(R.string.store_status_suspended_contact, store.name),
                    style = MaterialTheme.typography.bodyMedium,
                    color = EmbunColors.Muted,
                )
            else -> Unit
        }
    }
}

/** How each status looks; a status the app does not know shows the server's label. */
private class StatusLook(
    val icon: Int,
    val tone: StatusTone,
    val badge: Int?,
    val title: Int?,
    val body: Int?,
) {
    companion object {
        fun of(status: StoreStatus): StatusLook =
            when (status) {
                StoreStatus.Pending ->
                    StatusLook(
                        R.drawable.ic_status_clock,
                        StatusTone.Calm,
                        R.string.store_status_pending_badge,
                        R.string.store_status_pending_title,
                        R.string.store_status_pending_body,
                    )
                StoreStatus.Rejected ->
                    StatusLook(
                        R.drawable.ic_status_rejected,
                        StatusTone.Danger,
                        R.string.store_status_rejected_badge,
                        R.string.store_status_rejected_title,
                        R.string.store_status_rejected_body,
                    )
                StoreStatus.Suspended ->
                    StatusLook(
                        R.drawable.ic_status_suspended,
                        StatusTone.Danger,
                        R.string.store_status_suspended_badge,
                        R.string.store_status_suspended_title,
                        R.string.store_status_suspended_body,
                    )
                StoreStatus.Active, StoreStatus.Unknown -> StatusLook(R.drawable.ic_status_clock, StatusTone.Neutral, null, null, null)
            }
    }
}

/** The way from account to buyers, with the admin's review as the current step. */
@Composable
private fun PendingSteps() {
    Column {
        Step(StepState.Done, R.string.store_status_step_account, R.string.store_status_step_done, last = false)
        Step(StepState.Done, R.string.store_status_step_store, R.string.store_status_step_done, last = false)
        Step(StepState.Current, R.string.store_status_step_review, R.string.store_status_step_review_time, last = false)
        Step(StepState.Todo, R.string.store_status_step_live, R.string.store_status_step_live_detail, last = true)
    }
}

private enum class StepState { Done, Current, Todo }

@Composable
private fun Step(
    state: StepState,
    title: Int,
    detail: Int,
    last: Boolean,
) {
    Row(
        modifier = Modifier.alpha(if (state == StepState.Todo) TODO_ALPHA else 1f),
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            StepDot(state)
            if (!last) Box(Modifier.width(2.dp).height(24.dp).background(EmbunColors.Line))
        }
        Column(Modifier.padding(bottom = if (last) 0.dp else 8.dp)) {
            Text(
                text = stringResource(title),
                style = MaterialTheme.typography.bodyLarge.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp),
                color = MaterialTheme.colorScheme.onSurface,
            )
            Text(
                text = stringResource(detail),
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
            )
        }
    }
}

@Composable
private fun StepDot(state: StepState) {
    val primary = MaterialTheme.colorScheme.primary
    when (state) {
        StepState.Done ->
            Box(Modifier.size(28.dp).background(primary, CircleShape), contentAlignment = Alignment.Center) {
                Icon(painterResource(R.drawable.ic_check), contentDescription = null, tint = Color.White, modifier = Modifier.size(15.dp))
            }
        StepState.Current -> Box(Modifier.size(28.dp).background(CurrentStepFill, CircleShape).border(3.dp, primary, CircleShape))
        // A dashed ring: a step not reached yet.
        StepState.Todo ->
            Box(
                Modifier.size(28.dp).drawBehind {
                    drawCircle(
                        color = EmbunColors.Muted,
                        radius = size.minDimension / 2 - 1.dp.toPx(),
                        style = Stroke(width = 2.dp.toPx(), pathEffect = PathEffect.dashPathEffect(floatArrayOf(4.dp.toPx(), 3.dp.toPx()))),
                    )
                },
            )
    }
}

/** The admin's reason, as plain text: no markup or link is turned on. */
@Composable
private fun RejectionNote(reason: String) {
    Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = stringResource(R.string.store_status_rejected_note),
            style = MaterialTheme.typography.labelMedium.copy(fontWeight = FontWeight.Bold),
            color = EmbunColors.Muted,
        )
        Text(text = reason, style = MaterialTheme.typography.bodyLarge, color = MaterialTheme.colorScheme.onSurface)
    }
}

private const val TODO_ALPHA = 0.6f
private val CurrentStepFill = Color(0xFFE3EEE7).copy(alpha = 0.85f)

@Preview(widthDp = 390, heightDp = 1080)
@Composable
private fun StoreStatusScreenPreview() {
    SeafoodTheme {
        StoreStatusScreen(
            uiState =
                StoreStatusUiState(
                    isLoading = false,
                    store = StoreStatusInfo("UD Kepiting Pak Budi", StoreStatus.Pending, "Menunggu Persetujuan", null),
                ),
            onEvent = {},
            onStartProducts = {},
        )
    }
}
