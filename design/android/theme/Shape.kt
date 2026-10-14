package com.seafoodmarketplace.seller.ui.theme

import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Shapes
import androidx.compose.ui.unit.dp

// Radii from design/tokens.json: photo 16, option 18, card 22, feature 28, pill for
// buttons, chips, labels, and the search field.
val PhotoShape = RoundedCornerShape(16.dp)
val OptionShape = RoundedCornerShape(18.dp)
val CardShape = RoundedCornerShape(22.dp)
val FeatureShape = RoundedCornerShape(28.dp)
val PillShape = RoundedCornerShape(percent = 50)

val AppShapes = Shapes(
    small = PhotoShape,
    medium = CardShape,
    large = FeatureShape,
)
