package io.github.saptooaji.seafood.seller.feature.settings.ui

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
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.core.content.ContextCompat
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBottomBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.map.StoreMap
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormFields
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormState

private val LOCATION_PERMISSIONS = arrayOf(Manifest.permission.ACCESS_COARSE_LOCATION, Manifest.permission.ACCESS_FINE_LOCATION)

/**
 * The store form destination: wires [EditStoreViewModel], the location
 * permission, and the MapLibre map to the stateless screen. The map starts at
 * the store's pin; the permission is asked for only when "Lokasi saya" is pressed.
 *
 * @param onDone called once the form has ended, with how it ended; Back calls it with null
 */
@Composable
fun EditStoreScreen(
    onDone: (EditStoreResult?) -> Unit,
    viewModel: EditStoreViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    val context = LocalContext.current
    val permission =
        rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { granted ->
            viewModel.onEvent(if (granted.values.any { it }) EditStoreEvent.LocationGranted else EditStoreEvent.LocationDenied)
        }

    LaunchedEffect(uiState.result) {
        uiState.result?.let(onDone)
    }

    EditStoreScreen(
        uiState = uiState,
        onEvent = viewModel::onEvent,
        onUseMyLocation = {
            if (context.hasLocationPermission()) {
                viewModel.onEvent(EditStoreEvent.LocationGranted)
            } else {
                permission.launch(LOCATION_PERMISSIONS)
            }
        },
        onBack = { onDone(null) },
        map = { modifier ->
            StoreMap(
                target = uiState.camera.target,
                move = uiState.camera.move,
                onPinMoved = { viewModel.onEvent(EditStoreEvent.PinMoved(it)) },
                modifier = modifier,
            )
        },
    )
}

private fun Context.hasLocationPermission() =
    LOCATION_PERMISSIONS.any { ContextCompat.checkSelfPermission(this, it) == PackageManager.PERMISSION_GRANTED }

/**
 * The board VD-Profil-Toko: the form of Buka toko without step numbers,
 * filled from the server, with "Simpan perubahan" fixed to the bottom.
 *
 * @param map draws the map filling the given modifier (a plain box in tests)
 */
@Composable
fun EditStoreScreen(
    uiState: EditStoreUiState,
    onEvent: (EditStoreEvent) -> Unit,
    onUseMyLocation: () -> Unit,
    onBack: () -> Unit,
    map: @Composable (Modifier) -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            Column(Modifier.weight(1f).verticalScroll(rememberScrollState())) {
                EmbunTopBar(
                    title = stringResource(R.string.edit_store_title),
                    subtitle = stringResource(R.string.edit_store_subtitle),
                    onBack = onBack,
                    backLabel = stringResource(R.string.action_back),
                    backModifier = Modifier.testTag(EditStoreTags.BACK),
                )
                Column(
                    modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(14.dp),
                ) {
                    when {
                        uiState.trouble != null ->
                            OfflineContent(
                                onRetry = { onEvent(EditStoreEvent.Retry) },
                                retryModifier = Modifier.testTag(EditStoreTags.RETRY),
                                trouble = uiState.trouble,
                                modifier = Modifier.padding(top = 24.dp),
                            )
                        uiState.isLoading ->
                            Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
                                CircularProgressIndicator()
                            }
                        else -> {
                            uiState.generalError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
                            StoreFormFields(
                                form = uiState.form,
                                onFieldChange = { field, value -> onEvent(EditStoreEvent.FieldChanged(field, value)) },
                                onUseMyLocation = onUseMyLocation,
                                locationMessage = uiState.locationMessage,
                                map = map,
                                numbered = false,
                            )
                        }
                    }
                }
            }

            if (!uiState.isLoading && uiState.trouble == null) {
                GlassBottomBar {
                    PrimaryPillButton(
                        text = stringResource(if (uiState.isSaving) R.string.stock_saving else R.string.edit_store_save),
                        onClick = { onEvent(EditStoreEvent.Save) },
                        enabled = !uiState.isSaving,
                        modifier = Modifier.testTag(EditStoreTags.SAVE),
                    )
                }
            }
        }
    }
}

@Preview(widthDp = 390, heightDp = 1800)
@Composable
private fun EditStoreScreenPreview() {
    SeafoodTheme {
        EditStoreScreen(
            uiState =
                EditStoreUiState(
                    isLoading = false,
                    form =
                        StoreFormState(
                            name = "UD Kepiting Pak Budi",
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
            onBack = {},
            map = { Box(it) },
        )
    }
}

/** Muara Angke, as the board shows it. */
private val PREVIEW_PIN = GeoPoint(-6.1067, 106.7740)
