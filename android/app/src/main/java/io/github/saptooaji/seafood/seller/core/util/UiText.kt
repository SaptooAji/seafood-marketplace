package io.github.saptooaji.seafood.seller.core.util

import androidx.annotation.StringRes
import androidx.compose.runtime.Composable
import androidx.compose.ui.res.stringResource

/**
 * Text a ViewModel hands to the screen without holding a `Context`: a string
 * resource with its format arguments, or text that came as is.
 */
sealed interface UiText {
    data class Resource(
        @param:StringRes val id: Int,
        val args: List<Any> = emptyList(),
    ) : UiText

    data class Raw(
        val text: String,
    ) : UiText

    // The spread copies a list of one or two arguments; not worth avoiding.
    @Suppress("SpreadOperator")
    @Composable
    fun asString(): String =
        when (this) {
            is Resource -> stringResource(id, *args.toTypedArray())
            is Raw -> text
        }
}
