package io.github.saptooaji.seafood.seller.feature.shipping.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBottomBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.feature.product.ui.SegmentedChoice
import io.github.saptooaji.seafood.seller.feature.product.ui.SwitchRow
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType

/**
 * The Antar sendiri destination: wires [OwnDeliveryViewModel] to the stateless screen.
 *
 * @param onDone called once the form has ended, with how it ended; Back calls it with null
 */
@Composable
fun OwnDeliveryScreen(
    onDone: (OwnDeliveryResult?) -> Unit,
    viewModel: OwnDeliveryViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(uiState.result) {
        uiState.result?.let(onDone)
    }

    OwnDeliveryScreen(uiState = uiState, onEvent = viewModel::onEvent, onBack = { onDone(null) })
}

/** The boards VD-Antar-Sendiri and its states: radius, fee, buyer preview, and the switch. */
@Composable
fun OwnDeliveryScreen(
    uiState: OwnDeliveryUiState,
    onEvent: (OwnDeliveryEvent) -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            Column(Modifier.weight(1f).verticalScroll(rememberScrollState())) {
                EmbunTopBar(
                    title = stringResource(R.string.shipping_own_delivery_title),
                    subtitle = stringResource(R.string.shipping_own_delivery_subtitle),
                    onBack = onBack,
                    backLabel = stringResource(R.string.action_back),
                    backModifier = Modifier.testTag(OwnDeliveryTags.BACK),
                )
                Column(
                    Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                ) {
                    when {
                        uiState.trouble != null ->
                            OfflineContent(
                                onRetry = { onEvent(OwnDeliveryEvent.Retry) },
                                retryModifier = Modifier.testTag(OwnDeliveryTags.RETRY),
                                trouble = uiState.trouble,
                                modifier = Modifier.padding(top = 24.dp),
                            )
                        uiState.isLoading ->
                            Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator()
                            }
                        else -> Form(uiState, onEvent)
                    }
                }
            }
            if (!uiState.isLoading && uiState.trouble == null) {
                GlassBottomBar {
                    val label =
                        when {
                            uiState.isSaving -> R.string.stock_saving
                            uiState.isNew -> R.string.shipping_add
                            else -> R.string.settings_save
                        }
                    PrimaryPillButton(
                        text = stringResource(label),
                        onClick = { onEvent(OwnDeliveryEvent.Save) },
                        enabled = !uiState.isSaving,
                        modifier = Modifier.testTag(OwnDeliveryTags.SAVE),
                    )
                }
            }
        }
    }
}

@Composable
private fun Form(
    uiState: OwnDeliveryUiState,
    onEvent: (OwnDeliveryEvent) -> Unit,
) {
    val form = uiState.form
    uiState.generalError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
    GlassCard(Modifier.fillMaxWidth()) {
        FeeField(
            form = form,
            field = OwnDeliveryField.Radius,
            label = stringResource(R.string.shipping_radius),
            tag = OwnDeliveryTags.RADIUS,
            onEvent = onEvent,
            help = stringResource(R.string.shipping_radius_help),
            unit = stringResource(R.string.shipping_radius_unit),
        )
        Text(text = stringResource(R.string.shipping_fee_type), style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        SegmentedChoice(
            options = listOf(ShippingFeeType.Flat, ShippingFeeType.PerKm),
            selected = form.feeType,
            label = { stringResource(if (it == ShippingFeeType.Flat) R.string.shipping_fee_flat else R.string.shipping_fee_per_km) },
            onSelect = { onEvent(OwnDeliveryEvent.FeeTypePicked(it)) },
            tag = { if (it == ShippingFeeType.Flat) OwnDeliveryTags.CHOOSE_FLAT else OwnDeliveryTags.CHOOSE_PER_KM },
        )
        if (form.feeType == ShippingFeeType.Flat) {
            FeeField(
                form = form,
                field = OwnDeliveryField.FlatFee,
                label = stringResource(R.string.shipping_flat_fee),
                tag = OwnDeliveryTags.FLAT_FEE,
                onEvent = onEvent,
                help = stringResource(R.string.shipping_flat_fee_help),
                rupiah = true,
            )
        } else {
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Box(Modifier.weight(1f)) {
                    FeeField(
                        form,
                        OwnDeliveryField.FeePerKm,
                        stringResource(R.string.shipping_fee_per_km),
                        OwnDeliveryTags.FEE_PER_KM,
                        onEvent,
                        rupiah = true,
                    )
                }
                Box(Modifier.weight(1f)) {
                    FeeField(
                        form,
                        OwnDeliveryField.MinFee,
                        stringResource(R.string.shipping_min_fee),
                        OwnDeliveryTags.MIN_FEE,
                        onEvent,
                        rupiah = true,
                    )
                }
            }
        }
    }
    BuyerPreview(form)
    if (!uiState.isNew) {
        GlassCard(Modifier.fillMaxWidth(), spacing = 8.dp) {
            SwitchRow(
                title = stringResource(R.string.shipping_active),
                body = stringResource(R.string.shipping_active_body),
                checked = form.isActive,
                onCheckedChange = { onEvent(OwnDeliveryEvent.ActiveChanged(it)) },
                modifier = Modifier.testTag(OwnDeliveryTags.ACTIVE),
            )
            form.errors[OwnDeliveryField.Active]?.let {
                Text(text = it.asString(), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.DangerContent)
            }
        }
    }
}

@Composable
private fun FeeField(
    form: OwnDeliveryForm,
    field: OwnDeliveryField,
    label: String,
    tag: String,
    onEvent: (OwnDeliveryEvent) -> Unit,
    help: String? = null,
    unit: String? = null,
    rupiah: Boolean = false,
) {
    val value =
        when (field) {
            OwnDeliveryField.Radius -> form.radius
            OwnDeliveryField.FlatFee -> form.flatFee
            OwnDeliveryField.FeePerKm -> form.feePerKm
            OwnDeliveryField.MinFee -> form.minFee
            OwnDeliveryField.Active -> ""
        }
    GlassTextField(
        value = value,
        onValueChange = { onEvent(OwnDeliveryEvent.FieldChanged(field, it)) },
        label = label,
        error = form.errors[field]?.asString(),
        helpText = help,
        leading = if (rupiah) ({ Muted(stringResource(R.string.product_form_rupiah)) }) else null,
        trailing = unit?.let { { Muted(it) } },
        keyboardOptions =
            KeyboardOptions(
                keyboardType = if (field == OwnDeliveryField.Radius) KeyboardType.Decimal else KeyboardType.Number,
                imeAction = ImeAction.Next,
            ),
        modifier = Modifier.testTag(tag),
    )
}

@Composable
private fun Muted(text: String) {
    Text(text = text, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp), color = EmbunColors.Muted)
}

/** "Yang dilihat pembeli": the fee at a few distances, as the board draws it. */
@Composable
private fun BuyerPreview(form: OwnDeliveryForm) {
    Column(
        Modifier
            .fillMaxWidth()
            .background(PreviewGround, PreviewShape)
            .border(1.dp, EmbunColors.GlassEdge, PreviewShape)
            .padding(18.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(
            text = stringResource(R.string.shipping_preview_title),
            style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp),
        )
        ShippingPreview.DISTANCES.forEach { km ->
            val fee = ShippingPreview.fee(form, km)
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                Text(
                    text = stringResource(R.string.shipping_preview_distance, km),
                    style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp),
                    color = EmbunColors.Muted,
                    modifier = Modifier.weight(1f),
                )
                Text(
                    text = fee?.let(IndonesianNumbers::rupiah) ?: stringResource(R.string.shipping_preview_unavailable),
                    style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp, fontWeight = FontWeight.Bold),
                )
            }
        }
    }
}

private val PreviewGround = Color(0xFFE3EEE7).copy(alpha = 0.85f)
private val PreviewShape = RoundedCornerShape(28.dp)

@Preview(widthDp = 390, heightDp = 1350)
@Composable
private fun OwnDeliveryScreenPreview() {
    SeafoodTheme {
        OwnDeliveryScreen(
            uiState =
                OwnDeliveryUiState(
                    isLoading = false,
                    form = OwnDeliveryForm(radius = "20", feeType = ShippingFeeType.PerKm, feePerKm = "3000", minFee = "10000"),
                ),
            onEvent = {},
            onBack = {},
        )
    }
}
