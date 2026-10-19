package io.github.saptooaji.seafood.seller.core.designsystem.embun

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape

/**
 * A secondary action as a glass pill (white 85%, a thin white edge), such as
 * "Masuk dengan Google" with its logo in [leading].
 */
@Composable
fun GlassPillButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
    leading: (@Composable () -> Unit)? = null,
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = if (enabled) EmbunColors.GlassButton else EmbunColors.Disabled,
        contentColor = if (enabled) MaterialTheme.colorScheme.onSurface else EmbunColors.Muted,
        modifier =
            modifier
                .fillMaxWidth()
                .heightIn(min = 54.dp)
                .border(1.dp, EmbunColors.GlassEdge, PillShape),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 20.dp, vertical = 15.dp),
            horizontalArrangement = Arrangement.spacedBy(10.dp, Alignment.CenterHorizontally),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            leading?.invoke()
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontSize = MaterialTheme.typography.titleMedium.fontSize))
        }
    }
}

/** A thin line with a short word in the middle, such as "atau email" between two ways in. */
@Composable
fun TextDivider(
    text: String,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.weight(1f).height(1.dp).background(EmbunColors.Line))
        Text(text = text, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        Box(Modifier.weight(1f).height(1.dp).background(EmbunColors.Line))
    }
}

/** The heading of a numbered part of a long form: the number in a glass circle, then the title. */
@Composable
fun GlassSectionHeader(
    number: Int,
    title: String,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(
            modifier = Modifier.size(32.dp).background(EmbunColors.GlassButton, CircleShape),
            contentAlignment = Alignment.Center,
        ) {
            Text(text = number.toString(), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurface)
        }
        Text(
            text = title,
            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 19.sp, letterSpacing = (-0.02).em),
            color = MaterialTheme.colorScheme.onSurface,
            modifier = Modifier.weight(1f).semantics { heading() },
        )
    }
}
