package io.github.saptooaji.seafood.seller.core.designsystem.embun

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble

/**
 * The large round sign at the top of a status screen: a soft halo of [tone]
 * around a glass circle holding an icon and a short word. Decoration only;
 * the heading below says the same to a screen reader.
 */
@Composable
fun StatusOrb(
    @DrawableRes icon: Int,
    label: String,
    tone: StatusTone,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier =
            modifier
                .size(220.dp)
                .background(Brush.radialGradient(listOf(tone.halo.copy(alpha = 0.95f), Color.White.copy(alpha = 0.1f)))),
        contentAlignment = Alignment.Center,
    ) {
        Column(
            modifier =
                Modifier
                    .size(160.dp)
                    .background(EmbunColors.GlassStrong, CircleShape)
                    .border(1.dp, EmbunColors.GlassEdge, CircleShape),
            verticalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterVertically),
            horizontalAlignment = Alignment.CenterHorizontally,
        ) {
            Icon(painter = painterResource(icon), contentDescription = null, tint = tone.icon, modifier = Modifier.size(34.dp))
            Text(
                text = label,
                style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp),
                color = MaterialTheme.colorScheme.onSurface,
            )
        }
    }
}

/** The title and the line under it on a status screen, centred. */
@Composable
fun StatusHeading(
    title: String,
    body: String,
    modifier: Modifier = Modifier,
) {
    Column(modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(10.dp)) {
        Text(
            text = title,
            style =
                MaterialTheme.typography.displaySmall.copy(
                    fontWeight = FontWeight.Bold,
                    fontSize = 30.sp,
                    lineHeight = 32.sp,
                    letterSpacing = (-0.04).em,
                ),
            color = MaterialTheme.colorScheme.onBackground,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth().semantics { heading() },
        )
        Text(
            text = body,
            style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp),
            color = EmbunColors.Muted,
            textAlign = TextAlign.Center,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

/** A small coloured pill for a status, such as "Menunggu Persetujuan" or "Ditolak". */
@Composable
fun StatusChip(
    text: String,
    tone: StatusTone,
    modifier: Modifier = Modifier,
) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelSmall.copy(fontSize = 12.sp, fontWeight = FontWeight.Bold),
        color = tone.chipText,
        maxLines = 1,
        modifier = modifier.background(tone.chip, PillShape).padding(horizontal = 10.dp, vertical = 3.dp),
    )
}

/** A quiet action as plain green text in a pill-shaped touch area, such as "Keluar". */
@Composable
fun TextPillButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = Color.Transparent,
        contentColor = if (enabled) MaterialTheme.colorScheme.primary else EmbunColors.Muted,
        modifier = modifier.fillMaxWidth().heightIn(min = 44.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(12.dp)) {
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp))
        }
    }
}

/**
 * A whole screen saying the phone has no connection, or with [trouble] Server
 * that our server is down (#277), with a button to try again. The seller
 * stays signed in.
 */
@Composable
fun OfflineContent(
    onRetry: () -> Unit,
    modifier: Modifier = Modifier,
    retryModifier: Modifier = Modifier,
    trouble: ConnectionTrouble = ConnectionTrouble.NoInternet,
) {
    Column(
        modifier = modifier.fillMaxWidth(),
        verticalArrangement = Arrangement.spacedBy(18.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
    ) {
        when (trouble) {
            ConnectionTrouble.NoInternet -> {
                StatusOrb(icon = R.drawable.ic_status_offline, label = stringResource(R.string.offline_title), tone = StatusTone.Neutral)
                StatusHeading(title = stringResource(R.string.offline_title), body = stringResource(R.string.offline_body))
            }
            ConnectionTrouble.Server -> {
                StatusOrb(icon = R.drawable.ic_status_server, label = stringResource(R.string.server_down_badge), tone = StatusTone.Warm)
                StatusHeading(title = stringResource(R.string.server_down_title), body = stringResource(R.string.server_down_body))
            }
        }
        PrimaryPillButton(text = stringResource(R.string.offline_retry), onClick = onRetry, modifier = retryModifier)
    }
}
