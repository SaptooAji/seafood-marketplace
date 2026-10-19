package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.pluralStringResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import coil3.compose.AsyncImage
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusChip
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StatusTone
import io.github.saptooaji.seafood.seller.core.util.IndonesianNumbers
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatus

@Composable
internal fun ProductRow(
    product: Product,
    onClick: () -> Unit,
) {
    val (noteText, noteColor) =
        when {
            product.status == ProductStatus.Blocked ->
                stringResource(R.string.product_blocked_reason, product.blockReason.orEmpty()) to EmbunColors.DangerContent
            product.status == ProductStatus.Inactive -> stringResource(R.string.product_hidden) to EmbunColors.Muted
            product.hasStaleStock -> stringResource(R.string.product_stock_stale) to EmbunColors.WarnContent
            else -> product.categoryName to EmbunColors.Muted
        }
    Row(
        modifier =
            Modifier
                .fillMaxWidth()
                .alpha(if (product.status == ProductStatus.Inactive) INACTIVE_ALPHA else 1f)
                .clip(RowShape)
                .background(EmbunColors.Glass)
                .border(1.dp, EmbunColors.GlassEdge, RowShape)
                .clickable(onClick = onClick)
                .testTag(ProductListTags.row(product.id))
                .padding(start = 10.dp, top = 10.dp, bottom = 10.dp, end = 14.dp),
        verticalAlignment = Alignment.CenterVertically,
        horizontalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        ProductPhoto(product)
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(3.dp)) {
            Text(
                text = product.name,
                style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 16.sp),
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            // Plain text: a block reason written by an admin never turns markup on.
            Text(
                text = noteText,
                style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
                color = noteColor,
                maxLines = 2,
                overflow = TextOverflow.Ellipsis,
            )
            PriceLine(product)
        }
        Column(horizontalAlignment = Alignment.End, verticalArrangement = Arrangement.spacedBy(6.dp)) {
            StatusChip(text = stringResource(product.status.label), tone = product.status.tone)
            Text(
                text = pluralStringResource(R.plurals.product_variant_count, product.variants.size, product.variants.size),
                style = MaterialTheme.typography.labelSmall.copy(fontSize = 12.sp, fontWeight = FontWeight.Normal),
                color = EmbunColors.Muted,
            )
        }
    }
}

@Composable
private fun ProductPhoto(product: Product) {
    val url = product.thumbnailUrl
    if (url == null) {
        Box(
            modifier = Modifier.size(76.dp).background(EmbunColors.GlassButton, CircleShape).testTag(ProductListTags.noPhoto(product.id)),
            contentAlignment = Alignment.Center,
        ) {
            Image(painterResource(R.drawable.ic_logo_mark), contentDescription = null, modifier = Modifier.size(40.dp))
        }
    } else {
        AsyncImage(
            model = url,
            contentDescription = null,
            contentScale = ContentScale.Crop,
            placeholder = painterResource(R.drawable.ic_logo_mark),
            error = painterResource(R.drawable.ic_logo_mark),
            modifier = Modifier.size(76.dp).clip(CircleShape).background(EmbunColors.GlassButton),
        )
    }
}

@Composable
private fun PriceLine(product: Product) {
    val price = product.priceFrom
    if (price == null) {
        Text(
            text = stringResource(R.string.product_no_active_variant),
            style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
            color = EmbunColors.Muted,
        )
        return
    }
    val amount = IndonesianNumbers.rupiah(price.price)
    val unit = price.unitLabel.lowercase()
    Text(
        text = stringResource(if (price.activeVariants > 1) R.string.product_price_from else R.string.product_price, amount, unit),
        style = MaterialTheme.typography.bodySmall.copy(fontSize = 13.sp),
        color = EmbunColors.Muted,
    )
}

private val ProductStatus.label: Int
    get() =
        when (this) {
            ProductStatus.Active -> R.string.product_status_active
            ProductStatus.Inactive -> R.string.product_status_inactive
            ProductStatus.Blocked -> R.string.product_status_blocked
        }

private val ProductStatus.tone: StatusTone
    get() =
        when (this) {
            ProductStatus.Active -> StatusTone.Calm
            ProductStatus.Inactive -> StatusTone.Neutral
            ProductStatus.Blocked -> StatusTone.Danger
        }

private const val INACTIVE_ALPHA = 0.65f

/** The shape of a product row, also used by the loading rows. */
internal val RowShape = RoundedCornerShape(26.dp)
