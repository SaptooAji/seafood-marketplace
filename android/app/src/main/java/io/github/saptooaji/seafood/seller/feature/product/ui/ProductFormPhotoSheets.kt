package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.util.UiText
import java.io.File

/** A banner with links after its text, such as "Coba lagi" and "Hapus". */
@Composable
private fun PhotoBanner(
    text: String,
    warn: Boolean,
    links: List<Triple<String, String, () -> Unit>>,
) {
    val (background, content) =
        if (warn) EmbunColors.WarnBackground to EmbunColors.WarnContent else EmbunColors.DangerBackground to EmbunColors.DangerContent
    Column(
        Modifier
            .fillMaxWidth()
            .background(
                background,
                OptionShape,
            ).border(1.dp, EmbunColors.GlassEdge, OptionShape)
            .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text(text = text, style = MaterialTheme.typography.bodyMedium, color = content)
        if (links.isNotEmpty()) {
            Row(horizontalArrangement = Arrangement.spacedBy(20.dp)) {
                links.forEach { (label, tag, onClick) ->
                    Text(
                        text = label,
                        style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.ExtraBold),
                        color = content,
                        modifier =
                            Modifier
                                .clip(
                                    OptionShape,
                                ).clickable(role = Role.Button, onClick = onClick)
                                .padding(vertical = 4.dp)
                                .testTag(tag),
                    )
                }
            }
        }
    }
}

@Composable
internal fun FailedPhotoBanner(
    index: Int,
    key: String,
    message: UiText,
    onEvent: (ProductFormEvent) -> Unit,
) {
    PhotoBanner(
        text = stringResource(R.string.product_photo_failed, index + 1, message.asString()),
        warn = false,
        links =
            listOf(
                Triple(
                    stringResource(R.string.product_photo_retry),
                    ProductFormTags.photoRetry(key),
                ) { onEvent(ProductFormEvent.RetryPhoto(key)) },
                Triple(stringResource(R.string.product_photo_remove), ProductFormTags.photoRemove(key)) {
                    onEvent(ProductFormEvent.RemovePendingPhoto(key))
                },
            ),
    )
}

@Composable
internal fun PhotoNotice(
    notice: UiText,
    canResume: Boolean,
    onEvent: (ProductFormEvent) -> Unit,
) {
    val resume = stringResource(R.string.product_photo_resume)
    PhotoBanner(
        text = notice.asString(),
        warn = true,
        links =
            if (canResume) {
                listOf(
                    Triple(resume, ProductFormTags.PHOTO_RESUME) { onEvent(ProductFormEvent.ResumeUploads) },
                )
            } else {
                emptyList()
            },
    )
}

/** Where a new photo comes from (board VD-Produk-Form--pilih-sumber-foto). */
@Composable
fun BoxScope.PhotoSourceSheet(
    onPick: (PhotoSource) -> Unit,
    onDismiss: () -> Unit,
) {
    EmbunSheet(onDismiss = onDismiss) {
        SheetHeading(title = stringResource(R.string.product_photo_add_title), body = stringResource(R.string.product_photo_add_body))
        SourceOption(
            title = stringResource(R.string.product_photo_from_camera),
            body = stringResource(R.string.product_photo_from_camera_body),
            icon = R.drawable.ic_camera,
            modifier = Modifier.testTag(ProductFormTags.PHOTO_FROM_CAMERA),
            onClick = { onPick(PhotoSource.Camera) },
        )
        SourceOption(
            title = stringResource(R.string.product_photo_from_gallery),
            body = stringResource(R.string.product_photo_from_gallery_body),
            icon = R.drawable.ic_gallery,
            modifier = Modifier.testTag(ProductFormTags.PHOTO_FROM_GALLERY),
            onClick = { onPick(PhotoSource.Gallery) },
        )
        GlassPillButton(text = stringResource(R.string.product_photo_cancel), onClick = onDismiss)
    }
}

@Composable
private fun SourceOption(
    title: String,
    body: String,
    icon: Int,
    modifier: Modifier,
    onClick: () -> Unit,
) {
    Surface(
        onClick = onClick,
        shape = RoundedCornerShape(20.dp),
        color = EmbunColors.GlassButton,
        modifier = modifier.fillMaxWidth().heightIn(min = 64.dp).border(1.dp, EmbunColors.GlassEdge, RoundedCornerShape(20.dp)),
    ) {
        Row(
            Modifier.padding(horizontal = 16.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.size(44.dp).background(EmbunColors.HaloCalm, CircleShape), contentAlignment = Alignment.Center) {
                Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(22.dp))
            }
            Column(verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 16.sp))
                Text(text = body, style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp), color = EmbunColors.Muted)
            }
        }
    }
}

/**
 * A photo's actions (board VD-Produk-Form--aksi-foto), for a saved photo or
 * one waiting for a new product's save; the main photo has no "Jadikan utama".
 */
@Composable
fun BoxScope.PhotoActionsSheet(
    uiState: ProductFormUiState,
    key: String,
    onEvent: (ProductFormEvent) -> Unit,
) {
    val photos = uiState.photos.filter { it.isActionable }
    val index = photos.indexOfFirst { it.key == key }
    if (index < 0) return
    val photo = photos[index]
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissPhotoActions) }) {
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically) {
            AsyncImage(
                model = if (photo is SavedPhoto) photo.image.thumbnailUrl else File((photo as PendingPhoto).path),
                contentDescription = null,
                contentScale = ContentScale.Crop,
                modifier = Modifier.size(96.dp).clip(TileShape).background(EmbunColors.GlassButton),
            )
            SheetHeading(
                title = stringResource(R.string.product_photo_actions_title, index + 1, photos.size),
                body = stringResource(R.string.product_photo_actions_body),
            )
        }
        if (index > 0) {
            GlassPillButton(
                text = stringResource(R.string.product_photo_make_main),
                onClick = { onEvent(ProductFormEvent.MakeMainPhoto(key)) },
                enabled = uiState.canReorderPhotos,
                modifier = Modifier.testTag(ProductFormTags.PHOTO_MAKE_MAIN),
            )
        }
        DangerPillButton(
            text = stringResource(R.string.product_photo_delete),
            onClick = { onEvent(ProductFormEvent.DeletePhoto(key)) },
            modifier = Modifier.testTag(ProductFormTags.PHOTO_DELETE),
        )
        GlassPillButton(text = stringResource(R.string.product_photo_close), onClick = { onEvent(ProductFormEvent.DismissPhotoActions) })
    }
}

/** Asks before deleting a saved photo (board VD-Produk-Form--hapus-foto). */
@Composable
fun BoxScope.DeletePhotoSheet(onEvent: (ProductFormEvent) -> Unit) {
    EmbunSheet(onDismiss = { onEvent(ProductFormEvent.DismissDeletePhoto) }) {
        SheetHeading(title = stringResource(R.string.product_photo_delete_title), body = stringResource(R.string.product_photo_delete_body))
        DangerPillButton(
            text = stringResource(R.string.product_photo_delete),
            onClick = { onEvent(ProductFormEvent.ConfirmDeletePhoto) },
            filled = true,
            modifier = Modifier.testTag(ProductFormTags.CONFIRM_DELETE_PHOTO),
        )
        GlassPillButton(
            text = stringResource(R.string.product_photo_cancel),
            onClick = { onEvent(ProductFormEvent.DismissDeletePhoto) },
            modifier = Modifier.testTag(ProductFormTags.CANCEL_DELETE_PHOTO),
        )
    }
}

/**
 * The photo sheets over the form: where a new photo comes from while
 * [pickingSource], a saved photo's actions, and the delete question.
 * [onSourcePicked] gets null when the source sheet is closed without a pick.
 */
@Composable
fun BoxScope.PhotoSheets(
    uiState: ProductFormUiState,
    onEvent: (ProductFormEvent) -> Unit,
    pickingSource: Boolean,
    onSourcePicked: (PhotoSource?) -> Unit,
) {
    if (pickingSource) PhotoSourceSheet(onPick = onSourcePicked, onDismiss = { onSourcePicked(null) })
    uiState.photoActions?.let { PhotoActionsSheet(uiState, it, onEvent) }
    if (uiState.photoToDelete != null) DeletePhotoSheet(onEvent)
}
