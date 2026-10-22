package io.github.saptooaji.seafood.seller.feature.product.photo

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.gestures.detectTransformGestures
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.aspectRatio
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.requiredSize
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.layout.wrapContentSize
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clipToBounds
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.graphicsLayer
import androidx.compose.ui.input.pointer.pointerInput
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.IntOffset
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import coil3.compose.AsyncImage
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Ink
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import java.io.File
import kotlin.math.roundToInt

@Composable
fun PhotoCropScreen(
    onDone: (PhotoCropResult) -> Unit,
    viewModel: PhotoCropViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()
    LaunchedEffect(uiState.result) { uiState.result?.let(onDone) }
    PhotoCropScreen(uiState = uiState, onEvent = viewModel::onEvent)
}

/**
 * Cutting a photo to 1:1 or 4:3 (boards VD-Potong-Foto): a fixed frame the
 * photo is dragged and pinched under, on a dark ground so the photo stands
 * out. The frame shows only what is kept.
 */
@Composable
fun PhotoCropScreen(
    uiState: PhotoCropUiState,
    onEvent: (PhotoCropEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    BackHandler { onEvent(PhotoCropEvent.Cancel) }
    Column(
        modifier
            .fillMaxSize()
            .background(CropGround)
            .statusBarsPadding()
            .navigationBarsPadding(),
    ) {
        CropTopBar(onEvent)
        // Clipped, so a frame never spills over the buttons on a short screen.
        BoxWithConstraints(
            Modifier
                .weight(1f)
                .fillMaxWidth()
                .padding(horizontal = 16.dp)
                .clipToBounds(),
            contentAlignment = Alignment.Center,
        ) {
            val crop = uiState.crop
            val source = uiState.sourcePath
            when {
                crop != null && source != null -> {
                    // As wide as it can be while the whole frame still fits.
                    val ratio = crop.aspect.width.toFloat() / crop.aspect.height
                    val width = minOf(maxWidth, maxHeight * ratio)
                    CropFrame(source, crop, onEvent, Modifier.width(width).aspectRatio(ratio))
                }
                uiState.isLoading -> CircularProgressIndicator(color = Color.White)
            }
        }
        CropControls(uiState, onEvent)
    }
}

@Composable
private fun CropTopBar(onEvent: (PhotoCropEvent) -> Unit) {
    Row(Modifier.fillMaxWidth().padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
        IconButton(onClick = { onEvent(PhotoCropEvent.Cancel) }, modifier = Modifier.size(48.dp)) {
            Icon(painterResource(R.drawable.ic_close), stringResource(R.string.photo_crop_cancel), tint = Color.White)
        }
        Text(
            text = stringResource(R.string.photo_crop_title),
            color = Color.White,
            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp),
            modifier = Modifier.weight(1f),
        )
        IconButton(onClick = { onEvent(PhotoCropEvent.Rotate) }, modifier = Modifier.size(48.dp)) {
            Icon(painterResource(R.drawable.ic_rotate), stringResource(R.string.photo_crop_rotate), tint = Color.White)
        }
    }
}

/**
 * The photo under the frame. The frame is [PixelRect] of the turned photo
 * scaled to the screen; the photo is drawn turned and moved so that rect
 * lands in the frame.
 */
@Composable
private fun CropFrame(
    source: String,
    crop: CropState,
    onEvent: (PhotoCropEvent) -> Unit,
    modifier: Modifier = Modifier,
) {
    BoxWithConstraints(
        modifier
            .clipToBounds()
            .border(2.dp, Color.White),
    ) {
        val rect = crop.rect()
        val scale = constraints.maxWidth.toFloat() / rect.width
        val currentScale by rememberUpdatedState(scale)
        val density = LocalDensity.current
        val turned = with(density) { Pair((crop.turnedWidth * scale).toDp(), (crop.turnedHeight * scale).toDp()) }
        val upright = with(density) { Pair((crop.imageWidth * scale).toDp(), (crop.imageHeight * scale).toDp()) }
        Box(
            Modifier
                .fillMaxSize()
                .pointerInput(Unit) {
                    detectTransformGestures { _, pan, zoom, _ ->
                        if (zoom != 1f) onEvent(PhotoCropEvent.Zoom(zoom))
                        // Dragging the photo right moves the frame left over it.
                        if (pan != Offset.Zero) onEvent(PhotoCropEvent.Pan(-pan.x / currentScale, -pan.y / currentScale))
                    }
                },
        ) {
            Box(
                Modifier
                    .offset { IntOffset((-rect.left * scale).roundToInt(), (-rect.top * scale).roundToInt()) }
                    .wrapContentSize(Alignment.TopStart, unbounded = true)
                    .requiredSize(turned.first, turned.second),
            ) {
                AsyncImage(
                    model = File(source),
                    contentDescription = stringResource(R.string.photo_crop_image),
                    contentScale = ContentScale.FillBounds,
                    modifier =
                        Modifier
                            .align(Alignment.Center)
                            .wrapContentSize(unbounded = true)
                            .requiredSize(upright.first, upright.second)
                            .graphicsLayer { rotationZ = QUARTER * crop.quarterTurns },
                )
            }
            ThirdsGrid()
        }
    }
}

/** The rule-of-thirds lines inside the frame, as on the board. */
@Composable
private fun ThirdsGrid() {
    Canvas(Modifier.fillMaxSize()) {
        val line = Color.White.copy(alpha = GRID_ALPHA)
        for (i in 1..2) {
            val x = size.width * i / THIRDS
            val y = size.height * i / THIRDS
            drawLine(line, Offset(x, 0f), Offset(x, size.height), strokeWidth = 1.dp.toPx())
            drawLine(line, Offset(0f, y), Offset(size.width, y), strokeWidth = 1.dp.toPx())
        }
    }
}

@Composable
private fun CropControls(
    uiState: PhotoCropUiState,
    onEvent: (PhotoCropEvent) -> Unit,
) {
    Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        val message =
            when {
                uiState.error != null -> uiState.error.asString()
                uiState.isTooSmall -> stringResource(R.string.photo_crop_too_small, PHOTO_MIN_SIDE)
                else -> null
            }
        if (message != null) {
            Text(
                text = message,
                color = EmbunColors.DangerContent,
                style = MaterialTheme.typography.bodyMedium,
                modifier =
                    Modifier
                        .fillMaxWidth()
                        .background(CropAlert, RoundedCornerShape(18.dp))
                        .padding(horizontal = 14.dp, vertical = 12.dp),
            )
        }
        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp, Alignment.CenterHorizontally)) {
            CropAspect.entries.forEach { aspect ->
                AspectChip(aspect, selected = uiState.crop?.aspect == aspect, onClick = { onEvent(PhotoCropEvent.AspectPicked(aspect)) })
            }
        }
        Text(
            text = stringResource(R.string.photo_crop_hint, PHOTO_MIN_SIDE),
            color = Color.White.copy(alpha = HINT_ALPHA),
            style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
            modifier = Modifier.align(Alignment.CenterHorizontally),
        )
        UseButton(uiState, onEvent)
    }
}

@Composable
private fun AspectChip(
    aspect: CropAspect,
    selected: Boolean,
    onClick: () -> Unit,
) {
    val label =
        when (aspect) {
            CropAspect.Square -> R.string.photo_crop_square
            CropAspect.Wide -> R.string.photo_crop_wide
        }
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(12.dp),
        color = if (selected) Color.White else Color.White.copy(alpha = CHIP_ALPHA),
        contentColor = if (selected) Ink else Color.White,
        modifier =
            Modifier
                .heightIn(min = 44.dp)
                .semantics {
                    role = Role.RadioButton
                    this.selected = selected
                }.testTag(PhotoCropTags.aspect(aspect)),
    ) {
        Box(Modifier.padding(horizontal = 20.dp, vertical = 12.dp), contentAlignment = Alignment.Center) {
            Text(text = stringResource(label), fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun UseButton(
    uiState: PhotoCropUiState,
    onEvent: (PhotoCropEvent) -> Unit,
) {
    val enabled = uiState.canUse
    Surface(
        onClick = { onEvent(PhotoCropEvent.Use) },
        enabled = enabled,
        shape = PillShape,
        color = if (enabled) Color.White else CropDisabled,
        contentColor = if (enabled) Ink else CropDisabledText,
        modifier = Modifier.fillMaxWidth().heightIn(min = 54.dp).testTag(PhotoCropTags.USE),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(16.dp)) {
            Text(
                text = stringResource(if (uiState.isPreparing) R.string.photo_crop_preparing else R.string.photo_crop_use),
                style = MaterialTheme.typography.labelLarge.copy(fontSize = 16.sp),
            )
        }
    }
}

private const val QUARTER = 90f
private const val THIRDS = 3f
private const val GRID_ALPHA = 0.5f
private const val HINT_ALPHA = 0.75f
private const val CHIP_ALPHA = 0.12f

// The board's dark ground and its parts.
private val CropGround = Color(0xFF0E1813)
private val CropAlert = Color(0xFFFCE9E6)
private val CropDisabled = Color(0xFF575C52)
private val CropDisabledText = Color(0xFFECE6DA)
