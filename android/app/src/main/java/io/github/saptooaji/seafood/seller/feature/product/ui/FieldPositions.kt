package io.github.saptooaji.seafood.seller.feature.product.ui

import androidx.compose.runtime.Composable
import androidx.compose.runtime.remember
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.layout.LayoutCoordinates
import androidx.compose.ui.layout.onGloballyPositioned

/**
 * Where each field of the form sits, so the screen can scroll to the first
 * error. Plain fields, not state: reading them never recomposes.
 */
class FieldPositions {
    private var content: LayoutCoordinates? = null
    private val fields = mutableMapOf<FormField, LayoutCoordinates>()

    fun content(): Modifier = Modifier.onGloballyPositioned { content = it }

    fun of(field: FormField): Modifier = Modifier.onGloballyPositioned { fields[field] = it }

    /** The field's top within the scrolled content, or null when it is not on the form. */
    fun top(field: FormField): Int? {
        val container = content?.takeIf { it.isAttached }
        val coordinates = fields[field]?.takeIf { it.isAttached }
        if (container == null || coordinates == null) return null
        return container.localPositionOf(coordinates, Offset.Zero).y.toInt()
    }
}

@Composable
fun rememberFieldPositions(): FieldPositions = remember { FieldPositions() }
