package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.gestures.detectDragGesturesAfterLongPress
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableFloatStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.compose.ui.zIndex
import coil3.compose.AsyncImage
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassSectionHeader
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Ink
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import java.io.File
import kotlin.math.roundToInt

/**
 * Section 1, the photos (#241, boards VD-Produk-Form--foto-*): a row of
 * thumbnails, the first marked as the main one, and a tile to add one. A
 * saved photo opens its actions on a tap and moves on a long press and drag.
 * A new product adds photos after it is saved (#282).
 */
@Composable
fun PhotoSection(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    onAdd: () -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 1, title = stringResource(R.string.product_form_section_photos))
        if (!uiState.isEdit) {
            PhotoHelp(stringResource(R.string.product_photo_after_save))
            return@GlassCard
        }
        PhotoRow(uiState, onEvent, onAdd)
        uiState.photos.forEachIndexed { index, photo ->
            val status = (photo as? PendingPhoto)?.status
            if (status is PendingStatus.Failed) FailedPhotoBanner(index, photo.key, status.message, onEvent)
        }
        uiState.photoNotice?.let { notice -> PhotoNotice(notice, canResume = uiState.photos.any { it.isPaused }, onEvent) }
        PhotoHelp(
            when {
                uiState.photos.isEmpty() -> stringResource(R.string.product_photo_none)
                uiState.photos.size >= MAX_PHOTOS -> stringResource(R.string.product_photo_full, MAX_PHOTOS)
                else -> stringResource(R.string.product_photo_count, uiState.photos.size, MAX_PHOTOS)
            },
        )
    }
}

private val FormPhoto.isPaused: Boolean get() = this is PendingPhoto && status == PendingStatus.Paused

@Composable
private fun PhotoHelp(text: String) {
    Text(text = text, style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp), color = EmbunColors.Muted)
}

/** The thumbnails; a long press lifts a saved photo, and dropping it sends the new order. */
@Composable
private fun PhotoRow(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    onAdd: () -> Unit,
) {
    val step = with(LocalDensity.current) { (TILE + GAP).toPx() }
    val savedCount = uiState.photos.count { it is SavedPhoto }
    val canReorder by rememberUpdatedState(uiState.canReorderPhotos)
    var dragging by remember { mutableStateOf<Int?>(null) }
    var dragX by remember { mutableFloatStateOf(0f) }
    Row(
        Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(vertical = 8.dp),
        horizontalArrangement = Arrangement.spacedBy(GAP),
    ) {
        uiState.photos.forEachIndexed { index, photo ->
            val lifted = dragging == index
            val drag =
                if (photo is SavedPhoto) {
                    Modifier.pointerInput(photo.key) {
                        detectDragGesturesAfterLongPress(
                            onDragStart = { if (canReorder) dragging = index },
                            onDrag = { change, amount ->
                                if (dragging == index) {
                                    change.consume()
                                    dragX += amount.x
                                }
                            },
                            onDragEnd = {
                                val to = (index + (dragX / step).roundToInt()).coerceIn(0, savedCount - 1)
                                if (dragging == index) onEvent(ProductFormEvent.MovePhoto(from = index, to = to))
                                dragging = null
                                dragX = 0f
                            },
                            onDragCancel = {
                                dragging = null
                                dragX = 0f
                            },
                        )
                    }
                } else {
                    Modifier
                }
            PhotoTile(
                photo = photo,
                isMain = index == 0,
                onClick = { if (photo is SavedPhoto) onEvent(ProductFormEvent.OpenPhotoActions(photo.key)) },
                modifier =
                    drag
                        .zIndex(if (lifted) 1f else 0f)
                        .graphicsLayer {
                            if (lifted) {
                                translationX = dragX
                                rotationZ = LIFT_ROTATION
                                scaleX = LIFT_SCALE
                                scaleY = LIFT_SCALE
                            }
                        },
            )
        }
        AddTile(enabled = uiState.canAddPhoto, onClick = onAdd)
    }
}

@Composable
private fun PhotoTile(
    photo: FormPhoto,
    isMain: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    val status = (photo as? PendingPhoto)?.status
    val failed = status is PendingStatus.Failed
    Box(
        modifier
            .size(TILE)
            .shadow(if (failed) 0.dp else 2.dp, TileShape)
            .clip(TileShape)
            .then(if (failed) Modifier.border(3.dp, EmbunColors.DangerContent, TileShape) else Modifier)
            .clickable(enabled = photo is SavedPhoto, role = Role.Button, onClick = onClick)
            .testTag(ProductFormTags.photo(photo.key)),
    ) {
        AsyncImage(
            model =
                when (photo) {
                    is SavedPhoto -> photo.image.thumbnailUrl
                    is PendingPhoto -> File(photo.path)
                },
            contentDescription = null,
            contentScale = ContentScale.Crop,
            placeholder = painterResource(R.drawable.ic_logo_mark),
            error = painterResource(R.drawable.ic_logo_mark),
            modifier = Modifier.fillMaxSize().background(EmbunColors.GlassButton),
        )
        when (status) {
            is PendingStatus.Uploading -> UploadProgress(status.progress)
            PendingStatus.Queued -> TileOverlay(stringResource(R.string.product_photo_waiting))
            PendingStatus.Paused -> TileOverlay(stringResource(R.string.product_photo_held))
            is PendingStatus.Failed -> FailedMark()
            null -> Unit
        }
        if (isMain) {
            Text(
                text = stringResource(R.string.product_photo_main),
                color = Color.White,
                fontSize = 11.sp,
                fontWeight = FontWeight.Bold,
                modifier =
                    Modifier
                        .align(
                            Alignment.BottomStart,
                        ).padding(6.dp)
                        .background(Ink, PillShape)
                        .padding(horizontal = 8.dp, vertical = 2.dp),
            )
        }
    }
}

@Composable
private fun BoxScope.UploadProgress(progress: Float) {
    Column(
        Modifier.matchParentSize().background(Scrim),
        verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        CircularProgressIndicator(
            progress = { progress },
            color = Color.White,
            trackColor = Color.White.copy(alpha = TRACK_ALPHA),
            strokeWidth = 4.dp,
            modifier = Modifier.size(40.dp),
        )
        Text(text = "${(progress * PERCENT).roundToInt()}%", color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun BoxScope.TileOverlay(text: String) {
    Column(
        Modifier.matchParentSize().background(Scrim),
        verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        Icon(painterResource(R.drawable.ic_status_clock), contentDescription = null, tint = Color.White, modifier = Modifier.size(22.dp))
        Text(text = text, color = Color.White, fontSize = 12.sp, fontWeight = FontWeight.Bold)
    }
}

@Composable
private fun BoxScope.FailedMark() {
    Box(Modifier.matchParentSize().background(EmbunColors.DangerBackground.copy(alpha = FAILED_TINT)))
    Box(
        Modifier
            .align(Alignment.TopEnd)
            .padding(6.dp)
            .size(26.dp)
            .background(EmbunColors.DangerContent, CircleShape),
        contentAlignment = Alignment.Center,
    ) {
        Text(text = "!", color = Color.White, fontWeight = FontWeight.ExtraBold, fontSize = 15.sp)
    }
}

@Composable
private fun AddTile(
    enabled: Boolean,
    onClick: () -> Unit,
) {
    val label = stringResource(R.string.product_photo_add)
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = TileShape,
        color = Color.White.copy(alpha = ADD_ALPHA),
        contentColor = Ink,
        modifier =
            Modifier
                .size(TILE)
                .graphicsLayer { alpha = if (enabled) 1f else DISABLED_ALPHA }
                .border(2.dp, EmbunColors.Muted, TileShape)
                .semantics { contentDescription = label }
                .testTag(ProductFormTags.ADD_PHOTO),
    ) {
        Column(
            verticalArrangement = Arrangement.spacedBy(4.dp, Alignment.CenterVertically),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(painterResource(R.drawable.ic_camera), contentDescription = null, modifier = Modifier.size(24.dp))
            Text(text = label, fontSize = 13.sp, fontWeight = FontWeight.Bold)
        }
    }
}

private val TILE = 104.dp
private val GAP = 10.dp
internal val TileShape = RoundedCornerShape(18.dp)
private val Scrim = Color(0xFF0E1813).copy(alpha = 0.5f)
private const val PERCENT = 100
private const val TRACK_ALPHA = 0.35f
private const val FAILED_TINT = 0.35f
private const val ADD_ALPHA = 0.4f
private const val DISABLED_ALPHA = 0.45f
private const val LIFT_ROTATION = -3f
private const val LIFT_SCALE = 1.06f
