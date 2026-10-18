package io.github.saptooaji.seafood.seller.core.designsystem.embun

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape

/**
 * The top of a form screen: a round glass back button, then a large title
 * with an optional line under it.
 */
@Composable
fun EmbunTopBar(
    title: String,
    onBack: () -> Unit,
    backLabel: String,
    modifier: Modifier = Modifier,
    subtitle: String? = null,
    backModifier: Modifier = Modifier,
) {
    Column(
        modifier =
            modifier
                .fillMaxWidth()
                .statusBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 6.dp),
        verticalArrangement = Arrangement.spacedBy(14.dp),
    ) {
        Surface(
            onClick = onBack,
            shape = CircleShape,
            color = EmbunColors.GlassStrong,
            contentColor = MaterialTheme.colorScheme.onSurface,
            modifier =
                backModifier
                    .size(44.dp)
                    .border(1.dp, EmbunColors.GlassEdge, CircleShape),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(painter = painterResource(R.drawable.ic_arrow_back), contentDescription = backLabel, modifier = Modifier.size(22.dp))
            }
        }
        Column(Modifier.padding(horizontal = 4.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
            Text(
                text = title,
                style =
                    MaterialTheme.typography.displaySmall.copy(
                        fontWeight = FontWeight.Bold,
                        fontSize = 34.sp,
                        lineHeight = 36.sp,
                        letterSpacing = (-0.035).em,
                    ),
                color = MaterialTheme.colorScheme.onBackground,
                modifier = Modifier.semantics { heading() },
            )
            if (subtitle != null) {
                Text(text = subtitle, style = MaterialTheme.typography.bodyLarge.copy(fontSize = 15.sp), color = EmbunColors.Muted)
            }
        }
    }
}

/**
 * The glass bar fixed to the bottom of a form, holding its main action. It
 * stays above the keyboard and the navigation bar.
 */
@Composable
fun GlassBottomBar(
    modifier: Modifier = Modifier,
    content: @Composable ColumnScope.() -> Unit,
) {
    Column(
        modifier =
            modifier
                .fillMaxWidth()
                .background(EmbunColors.BottomBar, BottomBarShape)
                .border(1.dp, EmbunColors.GlassEdge, BottomBarShape)
                .navigationBarsPadding()
                .padding(start = 16.dp, end = 16.dp, top = 14.dp, bottom = 18.dp),
        verticalArrangement = Arrangement.spacedBy(8.dp),
        content = content,
    )
}

/**
 * Where a multi-screen flow is: one bar per step, filled up to [current]
 * (counted from 1), with each step's name under its bar.
 */
@Composable
fun StepIndicator(
    steps: List<String>,
    current: Int,
    modifier: Modifier = Modifier,
) {
    Row(modifier = modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        steps.forEachIndexed { index, step ->
            val done = index < current
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Box(
                    Modifier
                        .fillMaxWidth()
                        .height(5.dp)
                        .background(if (done) MaterialTheme.colorScheme.primary else EmbunColors.StepTodo, PillShape),
                )
                Text(
                    text = step,
                    style = MaterialTheme.typography.labelMedium,
                    color = if (done) MaterialTheme.colorScheme.onBackground else EmbunColors.Muted,
                    modifier = Modifier.heightIn(min = 18.dp),
                )
            }
        }
    }
}

private val BottomBarShape = RoundedCornerShape(topStart = 26.dp, topEnd = 26.dp)
