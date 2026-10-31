package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import kotlinx.coroutines.delay

/** The stock tab: wires [StockTodayViewModel] to the stateless screen. */
@Composable
fun StockTodayScreen(
    onOpenProducts: () -> Unit,
    onOpenBook: () -> Unit,
    banner: @Composable () -> Unit = {},
    changes: Int = 0,
    viewModel: StockTodayViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    // A change was recorded in the stock book: the counts are read again.
    LaunchedEffect(changes) {
        if (changes > 0) viewModel.onEvent(StockTodayEvent.Retry)
    }
    StockTodayScreen(uiState, viewModel::onEvent, onOpenProducts, onOpenBook, banner = banner)
}

/**
 * "Stok hari ini" as the board VD-Stok-Hari-Ini and its states draw it: the
 * day, a ring of variants counted today, a glass card per product with −, the
 * count, and +, and one save button above the tab capsule. The background and
 * the capsule come from the main shell.
 *
 * @param onOpenProducts the empty state's way to the products tab
 * @param onOpenBook the summary's link to the stock book (#244)
 * @param banner shown under the header, such as "menunggu verifikasi"
 */
@Composable
fun StockTodayScreen(
    uiState: StockTodayUiState,
    onEvent: (StockTodayEvent) -> Unit,
    onOpenProducts: () -> Unit,
    onOpenBook: () -> Unit = {},
    modifier: Modifier = Modifier,
    banner: @Composable () -> Unit = {},
) {
    val hasList = !uiState.isLoading && uiState.loadError == null && uiState.products.isNotEmpty()
    Box(modifier.fillMaxSize()) {
        Column(
            modifier =
                Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .statusBarsPadding()
                    .padding(PaddingValues(start = 16.dp, end = 16.dp, bottom = if (hasList) LIST_BOTTOM else PLAIN_BOTTOM)),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            Header(uiState)
            banner()
            when {
                uiState.isLoading -> Loading()
                uiState.loadError != null -> LoadFailed(uiState.loadError, onEvent)
                uiState.products.isEmpty() -> NoVariants(onOpenProducts)
                else -> {
                    Summary(uiState, onOpenBook)
                    uiState.products.forEach { ProductCard(it, uiState, onEvent) }
                }
            }
        }
        if (hasList) {
            SaveArea(uiState, onEvent, Modifier.align(Alignment.BottomCenter))
        }
        uiState.notice?.let { Notice(it.asString(), onEvent, Modifier.align(Alignment.BottomCenter)) }
    }
}

@Composable
private fun Header(uiState: StockTodayUiState) {
    Column(Modifier.padding(start = 4.dp, top = 26.dp), verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(text = headerDate(uiState.now), style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp), color = EmbunColors.Muted)
        Text(
            text = stringResource(R.string.stock_title),
            style = MaterialTheme.typography.displaySmall.copy(fontWeight = FontWeight.Bold, fontSize = 34.sp, letterSpacing = (-0.04).em),
            color = MaterialTheme.colorScheme.onBackground,
            modifier = Modifier.semantics { heading() },
        )
    }
}

@Composable
private fun Loading() {
    Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
        CircularProgressIndicator(color = MaterialTheme.colorScheme.primary)
    }
}

/** The one button: "Sama seperti kemarin" or "Simpan N perubahan", with a failed save above it. */
@Composable
private fun SaveArea(
    uiState: StockTodayUiState,
    onEvent: (StockTodayEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    Column(
        modifier = modifier.fillMaxWidth().padding(start = 16.dp, end = 16.dp, bottom = ABOVE_CAPSULE),
        verticalArrangement = Arrangement.spacedBy(6.dp),
    ) {
        uiState.saveError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
        PrimaryPillButton(
            text = uiState.saveLabel.asString(),
            onClick = { onEvent(StockTodayEvent.Save) },
            enabled = uiState.canSave,
            modifier = Modifier.testTag(StockTodayTags.SAVE),
        )
        Text(
            text = uiState.saveHelp.asString(),
            style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
            color = EmbunColors.Muted,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

/** "Stok dikonfirmasi untuk 7 ukuran, 05.52.", dark over the button for a few seconds. */
@Composable
private fun Notice(
    text: String,
    onEvent: (StockTodayEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    LaunchedEffect(text) {
        delay(NOTICE_MILLIS)
        onEvent(StockTodayEvent.NoticeShown)
    }
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = Color.White,
        modifier =
            modifier
                .padding(start = 16.dp, end = 16.dp, bottom = NOTICE_BOTTOM)
                .fillMaxWidth()
                .semantics { liveRegion = LiveRegionMode.Polite }
                .background(MaterialTheme.colorScheme.onBackground, OptionShape)
                .padding(horizontal = 16.dp, vertical = 14.dp),
    )
}

/** Room under the list for the button, its help line, and a failed save. */
private val LIST_BOTTOM = 260.dp
private val PLAIN_BOTTOM = 110.dp
private val ABOVE_CAPSULE = 92.dp
private val NOTICE_BOTTOM = 170.dp
private const val NOTICE_MILLIS = 4_000L
