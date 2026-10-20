package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.interaction.MutableInteractionSource
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.toggleable
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Switch
import androidx.compose.material3.SwitchDefaults
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Ink
import io.github.saptooaji.seafood.seller.core.designsystem.theme.LineStrong
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape

/**
 * One choice of a few, as a pill: ink when picked, glass otherwise, such as
 * a condition or a grade.
 */
@Composable
fun ChoicePill(
    text: String,
    selected: Boolean,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Box(
        modifier =
            modifier
                .heightIn(min = 44.dp)
                .background(if (selected) Ink else EmbunColors.GlassStrong, PillShape)
                .border(1.dp, if (selected) Ink else EmbunColors.GlassEdge, PillShape)
                .selectable(selected = selected, role = Role.RadioButton, onClick = onClick)
                .padding(horizontal = 14.dp, vertical = 10.dp),
        contentAlignment = Alignment.Center,
    ) {
        Text(
            text = text,
            style = MaterialTheme.typography.labelLarge.copy(fontSize = 14.sp),
            color = if (selected) Color.White else Ink,
            maxLines = 1,
            textAlign = TextAlign.Center,
        )
    }
}

/** Two ways to do one thing side by side, the picked one on white, such as ready stock or pre order. */
@Composable
fun <T> SegmentedChoice(
    options: List<T>,
    selected: T,
    label: @Composable (T) -> String,
    onSelect: (T) -> Unit,
    tag: (T) -> String,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier =
            modifier
                .fillMaxWidth()
                .background(Color.White.copy(alpha = 0.5f), PillShape)
                .padding(4.dp),
    ) {
        options.forEach { option ->
            val picked = option == selected
            Box(
                modifier =
                    Modifier
                        .weight(1f)
                        .heightIn(min = 44.dp)
                        .background(if (picked) Color.White else Color.Transparent, PillShape)
                        .selectable(selected = picked, role = Role.RadioButton, onClick = { onSelect(option) })
                        .testTag(tag(option))
                        .padding(horizontal = 8.dp),
                contentAlignment = Alignment.Center,
            ) {
                Text(
                    text = label(option),
                    style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp),
                    color = if (picked) Ink else EmbunColors.Muted,
                )
            }
        }
    }
}

/** A setting with a title, a line under it, and a switch, the whole row toggling it. */
@Composable
fun SwitchRow(
    title: String,
    body: String,
    checked: Boolean,
    onCheckedChange: (Boolean) -> Unit,
    modifier: Modifier = Modifier,
) {
    Row(
        modifier =
            modifier
                .fillMaxWidth()
                .toggleable(value = checked, role = Role.Switch, onValueChange = onCheckedChange),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
            Text(text = title, style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
            Text(text = body, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp), color = EmbunColors.Muted)
        }
        Switch(
            checked = checked,
            // The row toggles; the switch only shows the state.
            onCheckedChange = null,
            colors =
                SwitchDefaults.colors(
                    checkedTrackColor = MaterialTheme.colorScheme.primary,
                    uncheckedTrackColor = LineStrong,
                    uncheckedThumbColor = Color.White,
                    uncheckedBorderColor = LineStrong,
                ),
        )
    }
}

/** A destructive action as a red pill, such as "Hapus produk". */
@Composable
fun DangerPillButton(
    text: String,
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
    filled: Boolean = false,
    enabled: Boolean = true,
) {
    Surface(
        onClick = onClick,
        enabled = enabled,
        shape = PillShape,
        color = if (filled) EmbunColors.DangerContent else EmbunColors.GlassStrong,
        contentColor = if (filled) Color.White else EmbunColors.DangerContent,
        modifier = modifier.fillMaxWidth().heightIn(min = if (filled) 54.dp else 48.dp),
    ) {
        Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 20.dp, vertical = 14.dp)) {
            Text(text = text, style = MaterialTheme.typography.labelLarge.copy(fontSize = 16.sp))
        }
    }
}

/**
 * A glass sheet rising from the bottom over a dimmed screen, drawn in the
 * screen itself rather than a window, so Back and tests reach it like any
 * other part of the screen. Tapping the dim or Back dismisses it.
 */
@Composable
fun BoxScope.EmbunSheet(
    onDismiss: () -> Unit,
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    BackHandler(onBack = onDismiss)
    Box(
        Modifier
            .matchParentSize()
            .background(Scrim)
            .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null, onClick = onDismiss),
    )
    Column(
        modifier =
            modifier
                .align(Alignment.BottomCenter)
                .fillMaxWidth()
                .background(SheetGlass, SheetShape)
                .border(1.dp, EmbunColors.GlassEdge, SheetShape)
                // Taps inside the sheet stay in it.
                .clickable(interactionSource = remember { MutableInteractionSource() }, indication = null, onClick = {})
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 10.dp, bottom = 18.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
    ) {
        Box(
            Modifier
                .align(Alignment.CenterHorizontally)
                .width(36.dp)
                .height(4.dp)
                .background(LineStrong, PillShape),
        )
        content()
    }
}

/** The title of a sheet, with an optional line under it. */
@Composable
fun SheetHeading(
    title: String,
    modifier: Modifier = Modifier,
    body: String? = null,
) {
    Column(modifier, verticalArrangement = Arrangement.spacedBy(6.dp)) {
        Text(
            text = title,
            style =
                MaterialTheme.typography.titleLarge.copy(
                    fontWeight = FontWeight.ExtraBold,
                    fontSize = 22.sp,
                    letterSpacing = (-0.02).em,
                ),
            modifier = Modifier.semantics { heading() },
        )
        if (body != null) {
            Text(text = body, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp), color = EmbunColors.Muted)
        }
    }
}

/** A whole-screen box that fills what is left, for the loading and error states. */
@Composable
fun CenteredContent(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier = modifier.fillMaxSize().padding(horizontal = 16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp, Alignment.CenterVertically),
        horizontalAlignment = Alignment.CenterHorizontally,
        content = content,
    )
}

private val Scrim = Ink.copy(alpha = 0.32f)
private val SheetGlass = Color.White.copy(alpha = 0.94f)
private val SheetShape = RoundedCornerShape(topStart = 32.dp, topEnd = 32.dp)
