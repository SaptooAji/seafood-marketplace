package com.seafoodmarketplace.seller.ui.theme

import androidx.compose.material3.Typography
import androidx.compose.ui.text.ExperimentalTextApi
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.Font
import androidx.compose.ui.text.font.FontFamily
import androidx.compose.ui.text.font.FontVariation
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import com.seafoodmarketplace.seller.R

// Schibsted Grotesk is one variable font (res/font/schibsted_grotesk.ttf, SIL OFL 1.1);
// each weight is an instance of its wght axis.
@OptIn(ExperimentalTextApi::class)
private fun schibsted(weight: FontWeight) = Font(
    resId = R.font.schibsted_grotesk,
    weight = weight,
    variationSettings = FontVariation.Settings(FontVariation.weight(weight.weight)),
)

val SchibstedGrotesk = FontFamily(
    schibsted(FontWeight.Normal),
    schibsted(FontWeight.Medium),
    schibsted(FontWeight.SemiBold),
    schibsted(FontWeight.Bold),
    schibsted(FontWeight.ExtraBold),
)

// The web scale (design/tokens.json) mapped to Material 3 slots, sized for phones.
// Titles and prices are tightened with negative letter spacing, as on the web.
val AppTypography = Typography(
    displaySmall = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.ExtraBold, fontSize = 32.sp, lineHeight = 36.sp, letterSpacing = (-0.03).em),
    headlineSmall = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.ExtraBold, fontSize = 24.sp, lineHeight = 30.sp, letterSpacing = (-0.03).em),
    titleLarge = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.ExtraBold, fontSize = 20.sp, lineHeight = 26.sp, letterSpacing = (-0.02).em),
    titleMedium = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.Bold, fontSize = 16.sp, lineHeight = 22.sp),
    bodyLarge = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.Normal, fontSize = 16.sp, lineHeight = 24.sp),
    bodyMedium = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.Normal, fontSize = 14.sp, lineHeight = 20.sp),
    labelLarge = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.Bold, fontSize = 15.sp, lineHeight = 20.sp),
    labelMedium = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.SemiBold, fontSize = 13.sp, lineHeight = 18.sp),
    labelSmall = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.Medium, fontSize = 12.sp, lineHeight = 16.sp),
)

// Prices: "Rp185.000" in ExtraBold with tight tracking; the unit in labelSmall.
val PriceTextStyle = TextStyle(fontFamily = SchibstedGrotesk, fontWeight = FontWeight.ExtraBold, fontSize = 19.sp, letterSpacing = (-0.02).em)
