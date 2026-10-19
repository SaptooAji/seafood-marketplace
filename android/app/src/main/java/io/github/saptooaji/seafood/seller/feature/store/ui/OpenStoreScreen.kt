package io.github.saptooaji.seafood.seller.feature.store.ui

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.core.content.ContextCompat
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerAction
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBottomBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.map.StoreMap

private val LOCATION_PERMISSIONS = arrayOf(Manifest.permission.ACCESS_COARSE_LOCATION, Manifest.permission.ACCESS_FINE_LOCATION)

/**
 * The open-store destination: wires [OpenStoreViewModel], the location
 * permission, and the MapLibre map to the stateless screen. The permission is
 * asked for only when "Lokasi saya" is pressed; when it was granted before,
 * the map starts at the device.
 */
@Composable
fun OpenStoreScreen(
    onOpened: () -> Unit,
    onStoreStatus: () -> Unit,
    viewModel: OpenStoreViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val context = LocalContext.current
    val permission =
        rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { granted ->
            viewModel.onEvent(if (granted.values.any { it }) OpenStoreEvent.LocationGranted else OpenStoreEvent.LocationDenied)
        }

    LaunchedEffect(Unit) {
        // Only on the first visit: after a rotation the pin is already placed.
        if (uiState.form.pin == null && context.hasLocationPermission()) viewModel.onEvent(OpenStoreEvent.LocationGranted)
    }
    LaunchedEffect(uiState.opened) {
        if (uiState.opened) onOpened()
    }

    OpenStoreScreen(
        uiState = uiState,
        onEvent = viewModel::onEvent,
        onUseMyLocation = {
            if (context.hasLocationPermission()) {
                viewModel.onEvent(OpenStoreEvent.LocationGranted)
            } else {
                permission.launch(LOCATION_PERMISSIONS)
            }
        },
        onStoreStatus = onStoreStatus,
        map = { modifier ->
            StoreMap(
                target = uiState.camera.target,
                move = uiState.camera.move,
                onPinMoved = { viewModel.onEvent(OpenStoreEvent.PinMoved(it)) },
                modifier = modifier,
            )
        },
    )
}

private fun Context.hasLocationPermission() =
    LOCATION_PERMISSIONS.any { ContextCompat.checkSelfPermission(this, it) == PackageManager.PERMISSION_GRANTED }

/**
 * Step 2 of opening a store: the store itself, as the board VD-Buka-Toko
 * draws it, with the main action in a glass bar fixed to the bottom. The
 * account already exists, so there is no way back to registration.
 *
 * @param map draws the map filling the given modifier (a plain box in tests)
 */
@Composable
fun OpenStoreScreen(
    uiState: OpenStoreUiState,
    onEvent: (OpenStoreEvent) -> Unit,
    onUseMyLocation: () -> Unit,
    onStoreStatus: () -> Unit,
    map: @Composable (Modifier) -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            Column(
                Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState()),
            ) {
                EmbunTopBar(
                    title = stringResource(R.string.open_store_title),
                    subtitle = stringResource(R.string.open_store_subtitle),
                    onBack = null,
                    backLabel = stringResource(R.string.action_back),
                )
                Column(
                    modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    if (uiState.alreadyHasStore) {
                        GlassBanner(
                            text = stringResource(R.string.open_store_already_has_store),
                            tone = BannerTone.Warn,
                            action =
                                BannerAction(
                                    label = stringResource(R.string.open_store_view_status),
                                    onClick = onStoreStatus,
                                    modifier = Modifier.testTag(OpenStoreTags.STORE_STATUS),
                                ),
                        )
                    }
                    uiState.generalError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
                    StoreFormFields(
                        form = uiState.form,
                        onFieldChange = { field, value -> onEvent(OpenStoreEvent.FieldChanged(field, value)) },
                        onUseMyLocation = onUseMyLocation,
                        locationMessage = uiState.locationMessage,
                        map = map,
                    )
                }
            }

            GlassBottomBar {
                PrimaryPillButton(
                    text = stringResource(if (uiState.isSubmitting) R.string.open_store_submitting else R.string.open_store_submit),
                    onClick = { onEvent(OpenStoreEvent.Submit) },
                    enabled = uiState.canSubmit,
                    modifier = Modifier.testTag(OpenStoreTags.SUBMIT),
                )
                Text(
                    text = stringResource(R.string.open_store_review_time),
                    style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                    color = EmbunColors.Muted,
                    textAlign = TextAlign.Center,
                    modifier = Modifier.fillMaxWidth(),
                )
            }
        }
    }
}

@Preview(widthDp = 390, heightDp = 1800)
@Composable
private fun OpenStoreScreenPreview() {
    SeafoodTheme {
        OpenStoreScreen(
            uiState =
                OpenStoreUiState(
                    form =
                        StoreFormState(
                            name = "UD Kepiting Pak Budi",
                            description = "Pengepul kepiting bakau dari tambak Muara Angke sejak 2009.",
                            phone = "0812 3456 7890",
                            address = "Jl. Dermaga Ujung No. 12, Muara Angke",
                            city = "Jakarta Utara",
                            pin = PREVIEW_PIN,
                            bankName = "BCA",
                            bankAccountNo = "1234567890",
                            bankAccountName = "Budi Santoso",
                        ),
                ),
            onEvent = {},
            onUseMyLocation = {},
            onStoreStatus = {},
            map = { Box(it) },
        )
    }
}

/** Muara Angke, as the board shows it. */
private val PREVIEW_PIN = GeoPoint(-6.1067, 106.7740)
