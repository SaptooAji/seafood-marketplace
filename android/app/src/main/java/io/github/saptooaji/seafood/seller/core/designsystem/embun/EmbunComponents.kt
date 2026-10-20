package io.github.saptooaji.seafood.seller.core.designsystem.embun

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.PaddingValues
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentType
import androidx.compose.ui.semantics.error
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import io.github.saptooaji.seafood.seller.core.designsystem.theme.FeatureShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape

/** Where the sand band of the gradient sits, as in the boards (42%). */
private const val SAND_STOP = 0.42f

/** The Embun gradient behind a whole screen: green at the top, sand, then peach. */
@Composable
fun EmbunBackground(
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit,
) {
    Box(
        modifier =
            modifier.background(
                Brush.verticalGradient(
                    0f to EmbunColors.GradientTop,
                    SAND_STOP to EmbunColors.GradientMiddle,
                    1f to EmbunColors.GradientBottom,
                ),
            ),
        content = content,
    )
}

/** A glass panel: white at 62%, a thin white edge, radius 28, no blur. */
@Composable
fun GlassCard(
    modifier: Modifier = Modifier,
    contentPadding: PaddingValues = PaddingValues(18.dp),
    spacing: Dp = 14.dp,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier =
            modifier
                .background(EmbunColors.Glass, FeatureShape)
                .border(1.dp, EmbunColors.GlassEdge, FeatureShape)
                .padding(contentPadding),
        verticalArrangement = Arrangement.spacedBy(spacing),
        content = content,
    )
}

/**
 * A glass text field with its label above and its error or help text below.
 * [modifier] goes on the editable field itself, so a test tag finds the node
 * that takes text.
 *
 * @param contentType what autofill may fill in, such as [ContentType.EmailAddress]
 * @param leading and [trailing] sit in the field beside the text, such as `Rp` or `kg`
 */
@Composable
fun GlassTextField(
    value: String,
    onValueChange: (String) -> Unit,
    label: String,
    modifier: Modifier = Modifier,
    error: String? = null,
    helpText: String? = null,
    contentType: ContentType? = null,
    leading: (@Composable () -> Unit)? = null,
    trailing: (@Composable () -> Unit)? = null,
    keyboardOptions: KeyboardOptions = KeyboardOptions.Default,
    keyboardActions: KeyboardActions = KeyboardActions.Default,
    visualTransformation: VisualTransformation = VisualTransformation.None,
    singleLine: Boolean = true,
    minLines: Int = 1,
) {
    val edge = if (error != null) EmbunColors.DangerContent else EmbunColors.FieldEdge
    Column(Modifier.fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = label, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        BasicTextField(
            value = value,
            onValueChange = onValueChange,
            modifier =
                modifier
                    .fillMaxWidth()
                    .semantics {
                        if (contentType != null) this.contentType = contentType
                        if (error != null) error(error)
                    },
            textStyle = MaterialTheme.typography.bodyLarge.copy(color = MaterialTheme.colorScheme.onSurface),
            cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
            keyboardOptions = keyboardOptions,
            keyboardActions = keyboardActions,
            visualTransformation = visualTransformation,
            singleLine = singleLine,
            minLines = minLines,
            decorationBox = { inner ->
                Row(
                    modifier =
                        Modifier
                            .heightIn(min = 52.dp)
                            .background(EmbunColors.Field, OptionShape)
                            .border(1.5.dp, edge, OptionShape)
                            // Several lines start at the top, with room above and below.
                            .padding(horizontal = 18.dp, vertical = if (singleLine) 0.dp else 14.dp),
                    verticalAlignment = if (singleLine) Alignment.CenterVertically else Alignment.Top,
                    horizontalArrangement = Arrangement.spacedBy(6.dp),
                ) {
                    leading?.invoke()
                    Box(Modifier.weight(1f)) { inner() }
                    trailing?.invoke()
                }
            },
        )
        val below = error ?: helpText
        if (below != null) {
            Text(
                text = below,
                style = MaterialTheme.typography.bodyMedium,
                color = if (error != null) EmbunColors.DangerContent else EmbunColors.Muted,
                modifier = Modifier.padding(start = 4.dp),
            )
        }
    }
}

/** The main action: a green pill with a soft green shadow; glass when disabled. */
@Composable
fun PrimaryPillButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    enabled: Boolean = true,
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = if (enabled) MaterialTheme.colorScheme.primary else EmbunColors.Disabled,
        contentColor = if (enabled) MaterialTheme.colorScheme.onPrimary else EmbunColors.Muted,
        modifier =
            modifier
                .fillMaxWidth()
                .heightIn(min = 54.dp)
                .then(
                    if (enabled) {
                        Modifier.shadow(12.dp, PillShape, ambientColor = EmbunColors.ButtonShadow, spotColor = EmbunColors.ButtonShadow)
                    } else {
                        Modifier
                    },
                ),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 20.dp, vertical = 16.dp)) {
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontSize = MaterialTheme.typography.titleMedium.fontSize))
        }
    }
}

/** A small glass label, such as a filter or a status. */
@Composable
fun GlassChip(
    text: String,
    modifier: Modifier = Modifier,
) {
    Text(
        text = text,
        style = MaterialTheme.typography.labelMedium,
        color = MaterialTheme.colorScheme.onSurface,
        modifier =
            modifier
                .background(EmbunColors.GlassStrong, PillShape)
                .border(1.dp, EmbunColors.GlassEdge, PillShape)
                .padding(horizontal = 14.dp, vertical = 8.dp),
    )
}

enum class BannerTone { Warn, Danger }

/**
 * A message inside a panel that the screen reader announces when it appears,
 * with an optional bold link after it, such as "Lihat status toko".
 */
@Composable
fun GlassBanner(
    text: String,
    tone: BannerTone,
    modifier: Modifier = Modifier,
    action: BannerAction? = null,
) {
    val (background, content) =
        when (tone) {
            BannerTone.Warn -> EmbunColors.WarnBackground to EmbunColors.WarnContent
            BannerTone.Danger -> EmbunColors.DangerBackground to EmbunColors.DangerContent
        }
    Column(
        modifier =
            modifier
                .fillMaxWidth()
                .semantics(mergeDescendants = action == null) { liveRegion = LiveRegionMode.Polite }
                .background(background, OptionShape)
                .border(1.dp, EmbunColors.GlassEdge, OptionShape)
                .padding(horizontal = 14.dp, vertical = 12.dp),
        verticalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        Text(text = text, style = MaterialTheme.typography.bodyMedium, color = content)
        if (action != null) {
            Text(
                text = action.label,
                style = MaterialTheme.typography.bodyMedium.copy(fontWeight = FontWeight.ExtraBold),
                color = content,
                modifier =
                    action.modifier
                        .clip(OptionShape)
                        .clickable(role = Role.Button, onClick = action.onClick)
                        .padding(vertical = 4.dp),
            )
        }
    }
}

/** The link at the end of a [GlassBanner]; [modifier] goes on the link, such as a test tag. */
class BannerAction(
    val label: String,
    val onClick: () -> Unit,
    val modifier: Modifier = Modifier,
)
