package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.derivedStateOf
import androidx.compose.runtime.getValue
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.util.UiText
import kotlinx.coroutines.delay

/**
 * The stock book: wires [StockBookViewModel] to the stateless screen.
 *
 * @param onRecorded hears each change recorded here, so Stok hari ini reloads
 */
@Composable
fun StockBookScreen(
    onBack: () -> Unit,
    onRecorded: () -> Unit,
    viewModel: StockBookViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(uiState.recorded) {
        if (uiState.recorded > 0) onRecorded()
    }
    StockBookScreen(uiState, viewModel::onEvent, onBack)
}

/**
 * The stock book as the board VD-Buku-Stok draws it: a header with Back,
 * filter chips, movements grouped by day in glass cards, and "Catat
 * perubahan" at the bottom; the filter and record sheets open over it.
 */
@Composable
fun StockBookScreen(
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
    onBack: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().statusBarsPadding()) {
            Header(onBack)
            Chips(uiState, onEvent)
            Box(Modifier.weight(1f)) {
                when {
                    uiState.isLoading -> LoadingBook()
                    uiState.error != null -> BookFailed(uiState.error, onEvent)
                    uiState.movements.isEmpty() -> EmptyBook(uiState, onEvent)
                    else -> Movements(uiState, onEvent)
                }
            }
        }
        AddButton(
            onClick = { onEvent(StockBookEvent.OpenRecord) },
            modifier = Modifier.align(Alignment.BottomEnd).navigationBarsPadding().padding(end = 16.dp, bottom = 20.dp),
        )
        uiState.notice?.let { BookNotice(it, onEvent, Modifier.align(Alignment.BottomCenter)) }
        uiState.filterSheet?.let { FilterSheet(it, uiState, onEvent) }
        uiState.record?.let { RecordSheetView(it, uiState, onEvent) }
    }
}

@Composable
private fun Header(onBack: () -> Unit) {
    Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 6.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Surface(
            onClick = onBack,
            shape = CircleShape,
            color = EmbunColors.GlassStrong,
            contentColor = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.size(44.dp).border(1.dp, EmbunColors.GlassEdge, CircleShape).testTag(StockBookTags.BACK),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    painterResource(R.drawable.ic_arrow_back),
                    contentDescription = stringResource(R.string.stock_book_back),
                    modifier = Modifier.size(22.dp),
                )
            }
        }
        Column(Modifier.padding(horizontal = 4.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(
                text = stringResource(R.string.stock_book_title),
                style =
                    MaterialTheme.typography.displaySmall.copy(
                        fontWeight = FontWeight.Bold,
                        fontSize = 32.sp,
                        letterSpacing = (-0.04).em,
                    ),
                modifier = Modifier.semantics { heading() },
            )
            Text(
                text = stringResource(R.string.stock_book_subtitle),
                style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp),
                color = EmbunColors.Muted,
            )
        }
    }
}

/** Date, type, and variant; each opens the filter sheet, and is dark while it filters. */
@Composable
private fun Chips(
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    val filter = uiState.filter
    val type = filter.type
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(start = 16.dp, end = 16.dp, bottom = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        val open = { onEvent(StockBookEvent.OpenFilter) }
        FilterChip(
            dateRangeLabel(filter.from, filter.to).asString(),
            filter.from != null || filter.to != null,
            StockBookTags.DATE_CHIP,
            open,
        )
        FilterChip(
            if (type == null) stringResource(R.string.stock_book_all_types) else stringResource(typeName(type)),
            type != null,
            StockBookTags.TYPE_CHIP,
            open,
        )
        FilterChip(
            uiState.variantTitle(filter.variantId) ?: stringResource(R.string.stock_book_all_variants),
            filter.variantId != null,
            StockBookTags.VARIANT_CHIP,
            open,
        )
    }
}

@Composable
private fun FilterChip(
    text: String,
    active: Boolean,
    tag: String,
    onClick: () -> Unit,
) {
    Surface(
        onClick = onClick,
        shape = PillShape,
        color = if (active) MaterialTheme.colorScheme.onBackground else Color.White.copy(alpha = 0.65f),
        contentColor = if (active) Color.White else MaterialTheme.colorScheme.onBackground,
        modifier =
            Modifier
                .heightIn(min = 40.dp)
                .then(if (active) Modifier else Modifier.border(1.dp, EmbunColors.GlassEdge, PillShape))
                .semantics {
                    selected = active
                    role = Role.Button
                }.testTag(tag),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp)) {
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontWeight = FontWeight.Bold, fontSize = 14.sp))
        }
    }
}

/** Day by day, newest first; the next page is asked for a few lines before the end. */
@Composable
private fun Movements(
    uiState: StockBookUiState,
    onEvent: (StockBookEvent) -> Unit,
) {
    val listState = rememberLazyListState()
    val groups = remember(uiState.movements, uiState.zone) { uiState.movements.groupBy { dayOf(it, uiState.zone) }.toList() }
    val nearEnd by remember {
        derivedStateOf {
            val last =
                listState.layoutInfo.visibleItemsInfo
                    .lastOrNull()
                    ?.index ?: 0
            last >= listState.layoutInfo.totalItemsCount - LOAD_MORE_AHEAD
        }
    }
    LaunchedEffect(nearEnd, uiState.canLoadMore) {
        if (nearEnd && uiState.canLoadMore) onEvent(StockBookEvent.LoadMore)
    }
    LazyColumn(
        state = listState,
        contentPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = LIST_BOTTOM),
        verticalArrangement = Arrangement.spacedBy(18.dp),
        modifier = Modifier.fillMaxSize().testTag(StockBookTags.LIST),
    ) {
        items(groups, key = { (day, movements) -> "${day ?: "none"}-${movements.first().id}" }) { (day, movements) ->
            DayGroup(day?.let { dayHeading(it, uiState.today) }, movements, uiState.zone)
        }
        item { MoreFooter(uiState, onEvent) }
    }
}

@Composable
private fun AddButton(
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(14.dp),
        color = MaterialTheme.colorScheme.primary,
        contentColor = MaterialTheme.colorScheme.onPrimary,
        modifier = modifier.heightIn(min = 56.dp).testTag(StockBookTags.ADD),
    ) {
        Row(
            modifier = Modifier.padding(start = 18.dp, end = 22.dp, top = 16.dp, bottom = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(painterResource(R.drawable.ic_add), contentDescription = null, modifier = Modifier.size(22.dp))
            Text(text = stringResource(R.string.stock_book_add), fontWeight = FontWeight.ExtraBold, fontSize = 16.sp)
        }
    }
}

/** "Perubahan stok tercatat.", dark over the list for a few seconds. */
@Composable
private fun BookNotice(
    notice: UiText,
    onEvent: (StockBookEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    LaunchedEffect(notice) {
        delay(NOTICE_MILLIS)
        onEvent(StockBookEvent.NoticeShown)
    }
    Text(
        text = notice.asString(),
        style = MaterialTheme.typography.bodyMedium,
        color = Color.White,
        modifier =
            modifier
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, bottom = NOTICE_BOTTOM)
                .fillMaxWidth()
                .semantics { liveRegion = LiveRegionMode.Polite }
                .background(MaterialTheme.colorScheme.onBackground, OptionShape)
                .padding(horizontal = 16.dp, vertical = 14.dp),
    )
}

private const val LOAD_MORE_AHEAD = 2
private const val NOTICE_MILLIS = 4_000L
private val LIST_BOTTOM = 100.dp
private val NOTICE_BOTTOM = 88.dp
