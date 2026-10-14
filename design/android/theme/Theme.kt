package com.seafoodmarketplace.seller.ui.theme

import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color

// Material 3 roles for the Bakau dan Kepiting palette. Light only for now; the
// web has a dark variant in the design canvas that can be ported later.
private val LightColors = lightColorScheme(
    primary = Primary700,
    onPrimary = Color.White,
    primaryContainer = Primary50,
    onPrimaryContainer = Primary800,
    secondary = Ink,
    onSecondary = Color.White,
    secondaryContainer = Subtle,
    onSecondaryContainer = Ink,
    // Crab orange is for stock warnings and the logo dot, never for buttons.
    tertiary = Accent700,
    onTertiary = Color.White,
    tertiaryContainer = Accent50,
    onTertiaryContainer = Accent700,
    background = Sand,
    onBackground = Ink,
    surface = Color.White,
    onSurface = Ink,
    surfaceVariant = Subtle,
    onSurfaceVariant = Muted,
    surfaceContainerLowest = Color.White,
    surfaceContainerLow = Color.White,
    surfaceContainer = Sand,
    outline = LineStrong,
    outlineVariant = Line,
    error = Danger700,
    onError = Color.White,
    errorContainer = Danger50,
    onErrorContainer = Danger700,
)

@Composable
fun SeafoodTheme(content: @Composable () -> Unit) {
    MaterialTheme(
        colorScheme = LightColors,
        typography = AppTypography,
        shapes = AppShapes,
        content = content,
    )
}
