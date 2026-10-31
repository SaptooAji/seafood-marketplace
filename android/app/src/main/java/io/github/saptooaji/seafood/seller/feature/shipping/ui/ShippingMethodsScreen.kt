package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.LineStrong
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.product.ui.EmbunSheet
import io.github.saptooaji.seafood.seller.feature.product.ui.SheetHeading
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import kotlinx.coroutines.delay
import java.math.BigDecimal

/**
 * Cara terima pesanan: wires [ShippingMethodsViewModel] to the stateless screen.
 *
 * @param ownDeliveryResult how the own delivery form ended, handed back by the
 *   navigation; [onOwnDeliveryResultTaken] clears it once the list has reacted
 */
@Composable
fun ShippingMethodsScreen(
    onBack: () -> Unit,
    onOwnDelivery: () -> Unit,
    ownDeliveryResult: OwnDeliveryResult?,
    onOwnDeliveryResultTaken: () -> Unit,
    viewModel: ShippingMethodsViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(ownDeliveryResult) {
        if (ownDeliveryResult != null) {
            viewModel.onEvent(
                if (ownDeliveryResult == OwnDeliveryResult.NotOwner) ShippingMethodsEvent.NotOwner else ShippingMethodsEvent.Reload,
            )
            onOwnDeliveryResultTaken()
        }
    }

    ShippingMethodsScreen(uiState = uiState, onEvent = viewModel::onEvent, onOwnDelivery = onOwnDelivery, onBack = onBack)
}

/** The board VD-Metode-Pengiriman and its states: one glass panel per kind. */
@Composable
fun ShippingMethodsScreen(
    uiState: ShippingMethodsUiState,
    onEvent: (ShippingMethodsEvent) -> Unit,
    onOwnDelivery: () -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState())) {
            EmbunTopBar(
                title = stringResource(R.string.shipping_title),
                subtitle = stringResource(R.string.shipping_subtitle),
                onBack = onBack,
                backLabel = stringResource(R.string.action_back),
                backModifier = Modifier.testTag(ShippingTags.BACK),
            )
            Column(
                Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 96.dp),
                verticalArrangement = Arrangement.spacedBy(10.dp),
            ) {
                when {
                    uiState.trouble != null ->
                        OfflineContent(
                            onRetry = { onEvent(ShippingMethodsEvent.Retry) },
                            retryModifier = Modifier.testTag(ShippingTags.RETRY),
                            trouble = uiState.trouble,
                            modifier = Modifier.padding(top = 24.dp),
                        )
                    uiState.isLoading ->
                        Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
                            CircularProgressIndicator()
                        }
                    else -> Rows(uiState, onEvent, onOwnDelivery)
                }
            }
        }
        uiState.notice?.let { Notice(it, onEvent) }
        uiState.adding?.let { AddSheet(it, uiState.isAdding, onEvent) }
    }
}

@Composable
private fun Rows(
    uiState: ShippingMethodsUiState,
    onEvent: (ShippingMethodsEvent) -> Unit,
    onOwnDelivery: () -> Unit,
) {
    uiState.rows.forEach { row ->
        KindPanel(row, uiState.isOwner, onEvent, onOwnDelivery)
    }
    if (!uiState.isOwner) {
        Text(
            text = stringResource(R.string.shipping_owner_only_hint),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
            color = EmbunColors.Muted,
            modifier = Modifier.padding(4.dp),
        )
    }
}

/** One kind: icon, name, what it costs, and a switch once added or "Tambah" before. */
@Composable
private fun KindPanel(
    row: ShippingRow,
    isOwner: Boolean,
    onEvent: (ShippingMethodsEvent) -> Unit,
    onOwnDelivery: () -> Unit,
) {
    val method = row.method
    val summary = if (method != null) ShippingLabels.summary(method).asString() else ShippingLabels.about(row.type).asString()
    val inactive = if (method != null && !method.isActive) " " + stringResource(R.string.shipping_inactive) else ""
    GlassCard(
        Modifier
            .fillMaxWidth()
            .alpha(if (method == null) UNADDED_ALPHA else 1f)
            .semantics(mergeDescendants = true) {}
            .testTag(ShippingTags.row(row.type)),
        contentPadding = PaddingValues(16.dp),
        spacing = 12.dp,
    ) {
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Box(Modifier.size(44.dp).background(EmbunColors.GlassButton, CircleShape), contentAlignment = Alignment.Center) {
                Icon(painterResource(row.type.icon), contentDescription = null, modifier = Modifier.size(22.dp))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(
                    text = stringResource(row.type.title),
                    style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 16.sp),
                )
                Text(
                    text = summary + inactive,
                    style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                    color = EmbunColors.Muted,
                )
            }
            when {
                method != null ->
                    Switch(
                        checked = method.isActive,
                        onCheckedChange = { onEvent(ShippingMethodsEvent.Toggle(row.type, it)) },
                        enabled = isOwner,
                        colors =
                            SwitchDefaults.colors(
                                checkedTrackColor = MaterialTheme.colorScheme.primary,
                                uncheckedTrackColor = LineStrong,
                            ),
                        modifier = Modifier.testTag(ShippingTags.toggle(row.type)),
                    )
                isOwner && row.type != ShippingType.Pickup ->
                    SmallPill(
                        text = stringResource(R.string.shipping_add),
                        onClick = {
                            if (row.type ==
                                ShippingType.OwnDelivery
                            ) {
                                onOwnDelivery()
                            } else {
                                onEvent(ShippingMethodsEvent.Add(row.type))
                            }
                        },
                        modifier = Modifier.testTag(ShippingTags.add(row.type)),
                    )
            }
        }
        if (method != null && row.type == ShippingType.OwnDelivery && isOwner) {
            SmallPill(
                text = stringResource(R.string.shipping_edit_fees),
                onClick = onOwnDelivery,
                color = EditGround,
                modifier = Modifier.testTag(ShippingTags.EDIT_OWN_DELIVERY),
            )
        }
    }
}

@Composable
private fun SmallPill(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    color: Color = EmbunColors.GlassStrong,
) {
    Surface(onClick = onClick, shape = PillShape, color = color, modifier = modifier.heightIn(min = 44.dp)) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp)) {
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp))
        }
    }
}

/** A dark message at the bottom, as the board draws it; it goes away by itself. */
@Composable
private fun BoxScope.Notice(
    notice: ShippingNotice,
    onEvent: (ShippingMethodsEvent) -> Unit,
) {
    LaunchedEffect(notice) {
        delay(NOTICE_MILLIS)
        onEvent(ShippingMethodsEvent.NoticeShown)
    }
    val text =
        when (notice) {
            is ShippingNotice.Refused -> notice.message
            ShippingNotice.OwnerOnly -> stringResource(R.string.shipping_owner_only)
            ShippingNotice.NoInternet -> stringResource(R.string.error_network)
            ShippingNotice.Server -> stringResource(R.string.error_server)
        }
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp),
        color = Color.White,
        modifier =
            Modifier
                .align(Alignment.BottomCenter)
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, bottom = 24.dp)
                .fillMaxWidth()
                .background(MaterialTheme.colorScheme.onBackground, ToastShape)
                .padding(horizontal = 16.dp, vertical = 14.dp)
                .semantics { liveRegion = LiveRegionMode.Polite },
    )
}

/** The board VD-Metode-Pengiriman--tambah-ojek: a manual kind needs no form. */
@Composable
private fun BoxScope.AddSheet(
    type: ShippingType,
    isAdding: Boolean,
    onEvent: (ShippingMethodsEvent) -> Unit,
) {
    val (title, body) =
        if (type == ShippingType.Expedition) {
            R.string.shipping_add_expedition_title to R.string.shipping_add_expedition_body
        } else {
            R.string.shipping_add_ojol_title to R.string.shipping_add_ojol_body
        }
    EmbunSheet(onDismiss = { onEvent(ShippingMethodsEvent.CloseAdd) }) {
        SheetHeading(title = stringResource(title), body = stringResource(body))
        PrimaryPillButton(
            text = stringResource(if (isAdding) R.string.stock_saving else R.string.shipping_add_confirm),
            onClick = { onEvent(ShippingMethodsEvent.ConfirmAdd) },
            enabled = !isAdding,
            modifier = Modifier.testTag(ShippingTags.CONFIRM_ADD),
        )
        GlassPillButton(
            text = stringResource(R.string.shipping_add_cancel),
            onClick = { onEvent(ShippingMethodsEvent.CloseAdd) },
            modifier = Modifier.testTag(ShippingTags.CANCEL_ADD),
        )
    }
}

private val ShippingType.title: Int
    @StringRes get() =
        when (this) {
            ShippingType.Pickup -> R.string.shipping_type_pickup
            ShippingType.OwnDelivery -> R.string.shipping_type_own_delivery
            ShippingType.Ojol -> R.string.shipping_type_ojol
            ShippingType.Expedition -> R.string.shipping_type_expedition
        }

private val ShippingType.icon: Int
    @DrawableRes get() =
        when (this) {
            ShippingType.Pickup -> R.drawable.ic_pickup
            ShippingType.OwnDelivery -> R.drawable.ic_truck
            ShippingType.Ojol -> R.drawable.ic_store_pin
            ShippingType.Expedition -> R.drawable.ic_bank
        }

private const val NOTICE_MILLIS = 6_000L

/** A kind not added yet is drawn a little faded, as in the board. */
private const val UNADDED_ALPHA = 0.85f
private val EditGround = Color(0xFFCFE3D7).copy(alpha = 0.85f)
private val ToastShape = RoundedCornerShape(20.dp)

@Preview(widthDp = 390, heightDp = 1150)
@Composable
private fun ShippingMethodsScreenPreview() {
    SeafoodTheme {
        ShippingMethodsScreen(
            uiState =
                ShippingMethodsUiState(
                    isLoading = false,
                    isOwner = true,
                    rows = ShippingType.entries.map { type -> ShippingRow(type, PREVIEW.firstOrNull { it.type == type }) },
                ),
            onEvent = {},
            onOwnDelivery = {},
            onBack = {},
        )
    }
}

private val PREVIEW =
    listOf(
        ShippingMethod(id = 1, type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0, isActive = true),
        ShippingMethod(
            id = 2,
            type = ShippingType.OwnDelivery,
            feeType = ShippingFeeType.PerKm,
            maxRadiusKm = BigDecimal("20.00"),
            feePerKm = 3_000,
            minFee = 10_000,
            isActive = true,
        ),
    )
