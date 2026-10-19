package io.github.saptooaji.seafood.seller.feature.product.ui

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
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.runtime.snapshotFlow
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter

/** The products tab: wires [ProductListViewModel] to the stateless screen. */
@Composable
fun ProductListScreen(
    onAddProduct: () -> Unit,
    onOpenProduct: (Long) -> Unit,
    banner: @Composable () -> Unit = {},
    viewModel: ProductListViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    ProductListScreen(uiState, viewModel::onEvent, onAddProduct, onOpenProduct, banner = banner)
}

/** How close to the end of the list (in rows) the next page is asked for. */
private const val LOAD_MORE_AHEAD = 4

/**
 * The store's products as the board VD-Produk-Daftar draws them: a header with
 * search, status chips, and glass rows, with the add button above the tab
 * capsule. The background and the capsule come from the main shell.
 *
 * @param banner shown under the header, such as "menunggu verifikasi"
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ProductListScreen(
    uiState: ProductListUiState,
    onEvent: (ProductListEvent) -> Unit,
    onAddProduct: () -> Unit,
    onOpenProduct: (Long) -> Unit,
    modifier: Modifier = Modifier,
    banner: @Composable () -> Unit = {},
) {
    Box(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().statusBarsPadding()) {
            Header(uiState, onEvent)
            Box(Modifier.padding(horizontal = 16.dp)) { banner() }
            Filters(uiState.filter, onEvent)
            PullToRefreshBox(
                isRefreshing = uiState.isRefreshing,
                onRefresh = { onEvent(ProductListEvent.Refresh) },
                modifier = Modifier.weight(1f),
            ) {
                when (uiState.content) {
                    ProductListContent.Products -> ProductRows(uiState, onEvent, onOpenProduct)
                    ProductListContent.Loading -> LoadingRows()
                    ProductListContent.NoProducts -> Scrollable { NoProducts(onAddProduct) }
                    ProductListContent.NoResults -> Scrollable { NoResults(uiState, onEvent) }
                    ProductListContent.Error -> Scrollable { FirstPageError(uiState.error, onEvent) }
                }
            }
        }
        if (uiState.content == ProductListContent.Products) {
            AddButton(onAddProduct, Modifier.align(Alignment.BottomEnd).padding(end = 16.dp, bottom = 92.dp))
        }
    }
}

@Composable
private fun Header(
    uiState: ProductListUiState,
    onEvent: (ProductListEvent) -> Unit,
) {
    var searchOpen by rememberSaveable { mutableStateOf(false) }
    val showSearch = searchOpen || uiState.query.isNotEmpty()

    Column(
        modifier = Modifier.fillMaxWidth().padding(start = 20.dp, end = 16.dp, top = 26.dp, bottom = 12.dp),
        verticalArrangement = Arrangement.spacedBy(if (showSearch) 12.dp else 4.dp),
    ) {
        uiState.storeName?.let {
            Text(
                text = it,
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 14.sp),
                color = EmbunColors.Muted,
            )
        }
        if (showSearch) {
            SearchField(
                query = uiState.query,
                onQueryChange = { onEvent(ProductListEvent.QueryChanged(it)) },
                onClose = {
                    searchOpen = false
                    onEvent(ProductListEvent.QueryChanged(""))
                },
            )
        } else {
            Row(verticalAlignment = Alignment.Bottom) {
                Text(
                    text = stringResource(R.string.product_list_title),
                    style =
                        MaterialTheme.typography.displaySmall.copy(
                            fontWeight = FontWeight.Bold,
                            fontSize = 34.sp,
                            letterSpacing = (-0.04).em,
                        ),
                    color = MaterialTheme.colorScheme.onBackground,
                    modifier = Modifier.weight(1f).semantics { heading() },
                )
                RoundGlassButton(
                    icon = R.drawable.ic_search,
                    label = stringResource(R.string.product_search_open),
                    onClick = { searchOpen = true },
                    modifier = Modifier.testTag(ProductListTags.SEARCH_BUTTON),
                )
            }
        }
    }
}

@Composable
private fun SearchField(
    query: String,
    onQueryChange: (String) -> Unit,
    onClose: () -> Unit,
) {
    val label = stringResource(R.string.product_search_label)
    Row(
        modifier =
            Modifier
                .fillMaxWidth()
                .heightIn(min = 52.dp)
                .background(EmbunColors.Field, PillShape)
                .border(1.5.dp, EmbunColors.FieldEdge, PillShape)
                .padding(start = 18.dp, end = 4.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Icon(painterResource(R.drawable.ic_search), contentDescription = null, tint = EmbunColors.Muted, modifier = Modifier.size(20.dp))
        BasicTextField(
            value = query,
            onValueChange = onQueryChange,
            singleLine = true,
            textStyle = MaterialTheme.typography.bodyLarge.copy(color = MaterialTheme.colorScheme.onSurface),
            cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
            keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search),
            modifier =
                Modifier
                    .weight(1f)
                    .semantics { contentDescription = label }
                    .testTag(ProductListTags.SEARCH_FIELD),
        )
        RoundGlassButton(
            icon = R.drawable.ic_status_rejected,
            label = stringResource(R.string.product_search_clear),
            onClick = onClose,
            transparent = true,
            modifier = Modifier.testTag(ProductListTags.CLEAR_SEARCH),
        )
    }
}

@Composable
private fun RoundGlassButton(
    icon: Int,
    label: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    transparent: Boolean = false,
) {
    Surface(
        onClick = onClick,
        shape = CircleShape,
        color = if (transparent) Color.Transparent else EmbunColors.GlassStrong,
        contentColor = MaterialTheme.colorScheme.onSurface,
        modifier = modifier.size(44.dp).then(if (transparent) Modifier else Modifier.border(1.dp, EmbunColors.GlassEdge, CircleShape)),
    ) {
        Box(contentAlignment = Alignment.Center) {
            Icon(painterResource(icon), contentDescription = label, modifier = Modifier.size(20.dp))
        }
    }
}

@Composable
private fun Filters(
    selected: ProductStatusFilter,
    onEvent: (ProductListEvent) -> Unit,
) {
    Row(
        modifier =
            Modifier
                .fillMaxWidth()
                .horizontalScroll(
                    rememberScrollState(),
                ).padding(start = 16.dp, end = 16.dp, top = 12.dp, bottom = 12.dp),
        horizontalArrangement = Arrangement.spacedBy(8.dp),
    ) {
        ProductStatusFilter.entries.forEach { filter ->
            val chosen = filter == selected
            Text(
                text = stringResource(filter.label),
                style = MaterialTheme.typography.labelLarge.copy(fontSize = 14.sp),
                color = if (chosen) Color.White else MaterialTheme.colorScheme.onSurface,
                modifier =
                    Modifier
                        .clip(PillShape)
                        .background(if (chosen) MaterialTheme.colorScheme.onBackground else EmbunColors.GlassStrong)
                        .then(if (chosen) Modifier else Modifier.border(1.dp, EmbunColors.GlassEdge, PillShape))
                        .selectable(selected = chosen, role = Role.Tab, onClick = { onEvent(ProductListEvent.FilterChanged(filter)) })
                        .testTag(ProductListTags.filter(filter))
                        .heightIn(min = 40.dp)
                        .padding(horizontal = 16.dp, vertical = 10.dp),
            )
        }
    }
}

private val ProductStatusFilter.label: Int
    get() =
        when (this) {
            ProductStatusFilter.All -> R.string.product_filter_all
            ProductStatusFilter.Active -> R.string.product_status_active
            ProductStatusFilter.Inactive -> R.string.product_status_inactive
            ProductStatusFilter.Blocked -> R.string.product_status_blocked
        }

/** The bottom of the list leaves room for the add button and the tab capsule. */
private val ListPadding = PaddingValues(start = 16.dp, end = 16.dp, bottom = 170.dp)

@Composable
private fun ProductRows(
    uiState: ProductListUiState,
    onEvent: (ProductListEvent) -> Unit,
    onOpenProduct: (Long) -> Unit,
) {
    val listState = rememberLazyListState()
    val currentOnEvent by rememberUpdatedState(onEvent)
    val count = uiState.products.size

    LaunchedEffect(listState, count) {
        snapshotFlow {
            listState.layoutInfo.visibleItemsInfo
                .lastOrNull()
                ?.index ?: 0
        }.collect { last -> if (last >= count - LOAD_MORE_AHEAD) currentOnEvent(ProductListEvent.LoadMore) }
    }

    LazyColumn(
        state = listState,
        contentPadding = ListPadding,
        verticalArrangement = Arrangement.spacedBy(10.dp),
        modifier = Modifier.fillMaxSize().testTag(ProductListTags.LIST),
    ) {
        items(uiState.products, key = { it.id }) { product -> ProductRow(product, onClick = { onOpenProduct(product.id) }) }
        item(key = "footer") { ListFooter(uiState, onEvent) }
    }
}

@Composable
private fun AddButton(
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Surface(
        onClick = onClick,
        shape = CircleShape,
        color = MaterialTheme.colorScheme.primary,
        contentColor = MaterialTheme.colorScheme.onPrimary,
        modifier =
            modifier
                .size(60.dp)
                .shadow(14.dp, CircleShape, ambientColor = EmbunColors.ButtonShadow, spotColor = EmbunColors.ButtonShadow)
                .testTag(ProductListTags.ADD),
    ) {
        Box(contentAlignment = Alignment.Center) {
            Icon(
                painterResource(R.drawable.ic_add),
                contentDescription = stringResource(R.string.product_add),
                modifier = Modifier.size(26.dp),
            )
        }
    }
}

@Preview(widthDp = 390, heightDp = 900)
@Composable
private fun ProductListScreenPreview() {
    SeafoodTheme {
        ProductListScreen(
            uiState = ProductListUiState(storeName = "UD Kepiting Pak Budi", isLoading = false),
            onEvent = {},
            onAddProduct = {},
            onOpenProduct = {},
        )
    }
}
