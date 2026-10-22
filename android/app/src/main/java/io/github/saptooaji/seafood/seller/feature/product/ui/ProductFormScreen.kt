package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.BoxScope
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
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBottomBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusHeading
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode

/**
 * The product form destination: wires [ProductFormViewModel] to the
 * stateless screen and leaves once the form says how it ended. A picked
 * photo goes to the crop screen ([onCropPhoto]); what comes back as
 * [preparedPhoto] is uploaded, then [onPreparedPhotoTaken] clears it.
 */
@Composable
fun ProductFormScreen(
    onDone: (ProductFormResult) -> Unit,
    onCropPhoto: (String) -> Unit,
    preparedPhoto: String?,
    onPreparedPhotoTaken: () -> Unit,
    viewModel: ProductFormViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(uiState.result) {
        uiState.result?.let(onDone)
    }
    LaunchedEffect(preparedPhoto) {
        if (preparedPhoto != null) {
            viewModel.onEvent(ProductFormEvent.PhotoPrepared(preparedPhoto))
            onPreparedPhotoTaken()
        }
    }
    ProductFormScreen(uiState = uiState, onEvent = viewModel::onEvent, onAddPhoto = rememberPhotoPicker(onCropPhoto))
}

/**
 * Adding or editing a product (boards VD-Produk-Form and its states), with
 * the save button in a glass bar fixed to the bottom. Back asks before
 * throwing changes away; the view model decides whether there are any.
 */
@Composable
fun ProductFormScreen(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    modifier: Modifier = Modifier,
    onAddPhoto: (PhotoSource) -> Unit = {},
) {
    var pickingCategory by rememberSaveable { mutableStateOf(false) }
    var pickingPhotoSource by rememberSaveable { mutableStateOf(false) }
    BackHandler { onEvent(ProductFormEvent.Back) }

    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            val title = stringResource(if (uiState.isEdit) R.string.product_form_edit_title else R.string.product_form_add_title)
            when {
                uiState.notFound -> {
                    FormTopBar(title, onEvent)
                    NotFoundContent(onEvent)
                }
                uiState.loadError != null -> {
                    FormTopBar(title, onEvent)
                    LoadErrorContent(uiState.loadError, onEvent)
                }
                uiState.isLoading -> {
                    FormTopBar(title, onEvent)
                    LoadingContent()
                }
                else ->
                    FormContent(
                        uiState,
                        title,
                        onEvent,
                        onPickCategory = { pickingCategory = true },
                        onAddPhoto = { if (uiState.canAddPhoto) pickingPhotoSource = true },
                        modifier = Modifier.weight(1f),
                    )
            }
        }

        if (pickingCategory) {
            CategorySheet(
                categories = uiState.categories,
                selectedId = uiState.categoryId,
                onPick = { id ->
                    onEvent(ProductFormEvent.CategoryPicked(id))
                    pickingCategory = false
                },
                onDismiss = { pickingCategory = false },
            )
        }
        uiState.variantSheet?.let { VariantEditSheet(uiState, it, onEvent) }
        uiState.variantToDelete?.let { DeleteVariantSheet(it, onEvent) }
        PhotoSheets(
            uiState = uiState,
            onEvent = onEvent,
            pickingSource = pickingPhotoSource,
            onSourcePicked = { source ->
                pickingPhotoSource = false
                source?.let(onAddPhoto)
            },
        )
        uiState.suggestion?.let { SuggestionSheetContent(it, onEvent) }
        if (uiState.showDeleteDialog) DeleteSheet(uiState.name, onEvent)
        if (uiState.showDiscardDialog) DiscardSheet(onEvent)
    }
}

@Composable
private fun FormTopBar(
    title: String,
    onEvent: (ProductFormEvent) -> Unit,
) {
    EmbunTopBar(
        title = title,
        onBack = { onEvent(ProductFormEvent.Back) },
        backLabel = stringResource(R.string.action_back),
        backModifier = Modifier.testTag(ProductFormTags.BACK),
    )
}

@Composable
private fun FormContent(
    uiState: ProductFormUiState,
    title: String,
    onEvent: (ProductFormEvent) -> Unit,
    onPickCategory: () -> Unit,
    onAddPhoto: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val scroll = rememberScrollState()
    val positions = rememberFieldPositions()
    LaunchedEffect(uiState.scrollRequest) {
        val target = uiState.scrollTarget ?: return@LaunchedEffect
        // A little above the field, so its label shows too.
        positions.top(target)?.let { scroll.animateScrollTo((it - SCROLL_MARGIN).coerceAtLeast(0)) }
    }

    Column(modifier.verticalScroll(scroll).then(positions.content())) {
        FormTopBar(title, onEvent)
        Column(
            modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            if (uiState.isBlocked) {
                GlassBanner(text = stringResource(R.string.product_form_blocked, uiState.blockReason.orEmpty()), tone = BannerTone.Danger)
            }
            PhotoSection(uiState, onEvent, onAddPhoto)
            CategorySection(uiState, onEvent, onPickCategory, positions)
            StorySection(uiState, onEvent, positions)
            SellingSection(uiState, onEvent, positions)
            VariantSection(uiState, onEvent, positions)
            ActiveSection(uiState, onEvent)
            if (uiState.isEdit) {
                DangerPillButton(
                    text = stringResource(if (uiState.isDeleting) R.string.product_form_deleting else R.string.product_form_delete),
                    onClick = { onEvent(ProductFormEvent.Delete) },
                    enabled = !uiState.isBusy,
                    modifier = Modifier.testTag(ProductFormTags.DELETE),
                )
            }
        }
    }
    GlassBottomBar {
        uiState.variantMessage?.let { VariantMessage(it, onEvent) }
        uiState.generalError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
        val save = if (uiState.isEdit) R.string.product_form_save_edit else R.string.product_form_save_add
        val total = uiState.afterSaveTotal
        PrimaryPillButton(
            text =
                when {
                    total != null -> stringResource(R.string.product_form_uploading_photos, uiState.afterSaveCurrent, total)
                    uiState.isSaving -> stringResource(R.string.product_form_saving)
                    else -> stringResource(save)
                },
            onClick = { onEvent(ProductFormEvent.Save) },
            enabled = !uiState.isBusy,
            modifier = Modifier.testTag(ProductFormTags.SAVE),
        )
    }
}

@Composable
private fun LoadingContent() {
    val loading = stringResource(R.string.product_form_loading)
    CenteredContent(Modifier.semantics { contentDescription = loading }) {
        CircularProgressIndicator()
    }
}

@Composable
private fun LoadErrorContent(
    error: UiText,
    onEvent: (ProductFormEvent) -> Unit,
) {
    CenteredContent {
        if (error == UiText.Resource(R.string.error_network)) {
            OfflineContent(onRetry = { onEvent(ProductFormEvent.Retry) }, retryModifier = Modifier.testTag(ProductFormTags.RETRY))
        } else {
            GlassBanner(text = error.asString(), tone = BannerTone.Danger)
            PrimaryPillButton(
                text = stringResource(R.string.product_list_retry),
                onClick = { onEvent(ProductFormEvent.Retry) },
                modifier = Modifier.testTag(ProductFormTags.RETRY),
            )
        }
    }
}

@Composable
private fun NotFoundContent(onEvent: (ProductFormEvent) -> Unit) {
    Column(Modifier.padding(start = 16.dp, end = 16.dp, top = 12.dp)) {
        GlassCard(Modifier.fillMaxWidth()) {
            StatusHeading(
                title = stringResource(R.string.product_form_not_found),
                body = stringResource(R.string.product_form_not_found_body),
            )
            PrimaryPillButton(
                text = stringResource(R.string.product_form_back_to_list),
                onClick = { onEvent(ProductFormEvent.Back) },
                modifier = Modifier.testTag(ProductFormTags.BACK_TO_LIST),
            )
        }
    }
}

@Composable
private fun BoxScope.DeleteSheet(
    name: String,
    onEvent: (ProductFormEvent) -> Unit,
) {
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissDelete) }) {
        SheetHeading(
            title = stringResource(R.string.product_form_delete_title, name),
            body = stringResource(R.string.product_form_delete_body),
        )
        DangerPillButton(
            text = stringResource(R.string.product_form_delete),
            onClick = { onEvent(ProductFormEvent.ConfirmDelete) },
            filled = true,
            modifier = Modifier.testTag(ProductFormTags.CONFIRM_DELETE),
        )
        GlassPillButton(
            text = stringResource(R.string.product_form_delete_cancel),
            onClick = { onEvent(ProductFormEvent.DismissDelete) },
            modifier = Modifier.testTag(ProductFormTags.CANCEL_DELETE),
        )
    }
}

@Composable
private fun BoxScope.DiscardSheet(onEvent: (ProductFormEvent) -> Unit) {
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissDiscard) }) {
        SheetHeading(
            title = stringResource(R.string.product_form_discard_title),
            body = stringResource(R.string.product_form_discard_body),
        )
        DangerPillButton(
            text = stringResource(R.string.product_form_discard_confirm),
            onClick = { onEvent(ProductFormEvent.ConfirmDiscard) },
            filled = true,
            modifier = Modifier.testTag(ProductFormTags.CONFIRM_DISCARD),
        )
        GlassPillButton(
            text = stringResource(R.string.product_form_keep_editing),
            onClick = { onEvent(ProductFormEvent.DismissDiscard) },
            modifier = Modifier.testTag(ProductFormTags.KEEP_EDITING),
        )
    }
}

private const val SCROLL_MARGIN = 120

@Preview(widthDp = 390, heightDp = 2400)
@Composable
private fun ProductFormScreenPreview() {
    SeafoodTheme {
        ProductFormScreen(
            uiState =
                ProductFormUiState(
                    isLoading = false,
                    categories = listOf(PREVIEW_KEPITING),
                    categoryId = PREVIEW_BAKAU.id,
                    categoryPath = listOf("Kepiting", "Bakau"),
                    attributes = PREVIEW_BAKAU.attributes,
                    attributeValues = mapOf(PREVIEW_WEIGHT.id to "350", PREVIEW_GRADE.id to "Jantan"),
                    name = "Kepiting Bakau Super",
                    description = "Kepiting bakau jantan dari tambak Muara Angke, diikat dan dicek hidup setiap pagi sebelum dijual.",
                    seoDescription = "Kepiting bakau jantan grade super, hidup dari tambak Muara Angke.",
                    condition = ProductCondition.Live,
                    salesMode = SalesMode.PreOrder,
                    dailyQuota = "25",
                    leadTimeDays = "1",
                    cutoffTime = "15.00",
                    shelfLifeDays = "2",
                ),
            onEvent = {},
        )
    }
}

private val PREVIEW_WEIGHT = CategoryAttribute(10, "Berat per ekor", AttributeType.Number, emptyList(), unit = "g", isRequired = true)
private val PREVIEW_GRADE =
    CategoryAttribute(11, "Grade", AttributeType.Select, listOf("Jantan", "Betina"), unit = null, isRequired = false)
private val PREVIEW_BAKAU = Category(2, "Bakau", listOf(PREVIEW_WEIGHT, PREVIEW_GRADE), emptyList())
private val PREVIEW_KEPITING = Category(1, "Kepiting", listOf(PREVIEW_WEIGHT), listOf(PREVIEW_BAKAU))
