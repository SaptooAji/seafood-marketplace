package io.github.saptooaji.seafood.seller.core.designsystem.embun

import androidx.compose.ui.graphics.Color
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Accent700
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Danger700
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Primary700
import io.github.saptooaji.seafood.seller.core.designsystem.theme.Sand

/**
 * Variation D "Embun" from the design canvas (docs/ui-guidelines.md, Android):
 * a soft green-sand-peach gradient behind panels of semi-transparent white.
 * The glass has no blur; background blur needs API 31 and a new dependency.
 */
object EmbunColors {
    val GradientTop = Color(0xFFDDEBE2)
    val GradientMiddle = Sand
    val GradientBottom = Color(0xFFF8E9DE)

    /** Cards and sheets: white at 62% with a thin white edge. */
    val Glass = Color.White.copy(alpha = 0.62f)
    val GlassEdge = Color.White.copy(alpha = 0.85f)

    /** Text fields: a little more opaque than cards so the text reads well. */
    val Field = Color.White.copy(alpha = 0.78f)
    val FieldEdge = Color.White.copy(alpha = 0.95f)

    /** Chips and secondary buttons. */
    val GlassStrong = Color.White.copy(alpha = 0.75f)

    /** A secondary glass button, such as "Masuk dengan Google". */
    val GlassButton = Color.White.copy(alpha = 0.85f)

    /** A divider line on glass: ink at 10%. */
    val Line = Color(0xFF1B2E25).copy(alpha = 0.1f)

    /** The bar fixed to the bottom of a form. */
    val BottomBar = Color.White.copy(alpha = 0.72f)

    /** A step of a flow not reached yet. */
    val StepTodo = Color.White.copy(alpha = 0.7f)

    /** A disabled primary button: the glass shows through, the label is muted. */
    val Disabled = Color.White.copy(alpha = 0.55f)

    /** Muted text on the gradient; darker than the theme's so it keeps 4.5:1 on glass. */
    val Muted = Color(0xFF4F5A50)

    val ButtonShadow = Primary700.copy(alpha = 0.28f)
    val PhotoShadow = Color(0xFF1B2E25).copy(alpha = 0.25f)

    val WarnBackground = Color(0xFFFBEEE5).copy(alpha = 0.9f)
    val WarnContent = Accent700
    val DangerBackground = Color(0xFFFCE9E6).copy(alpha = 0.9f)
    val DangerContent = Danger700
}
