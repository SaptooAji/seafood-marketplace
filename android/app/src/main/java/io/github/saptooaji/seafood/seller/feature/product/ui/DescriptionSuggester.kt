package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Job
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/**
 * The AI description sheet (#240), part of [ProductFormViewModel]: a
 * suggestion is only shown until the seller uses it, through [edit], so the
 * form keeps it like anything typed.
 */
internal class DescriptionSuggester(
    private val products: ProductRepository,
    private val state: MutableStateFlow<ProductFormUiState>,
    private val scope: CoroutineScope,
    private val edit: ((ProductFormUiState) -> ProductFormUiState) -> Unit,
) : FormPart {
    /** The AI request still running, dropped when the seller closes its sheet. */
    private var suggesting: Job? = null

    override fun handle(event: ProductFormEvent): Boolean {
        when (event) {
            ProductFormEvent.SuggestDescription -> suggest()
            ProductFormEvent.UseSuggestion -> useSuggestion()
            ProductFormEvent.DismissSuggestion -> {
                suggesting?.cancel()
                state.update { it.copy(suggestion = null) }
            }
            else -> return false
        }
        return true
    }

    /** Asks the AI with the form as it is; nothing changes until the seller uses the answer. */
    private fun suggest() {
        val current = state.value
        if (!current.canSuggestDescription) return
        state.update { it.copy(suggestion = SuggestionSheet.Loading) }
        suggesting =
            scope.launch {
                val result = products.suggestDescription(current.draft())
                val errors = (result as? ApiResult.Invalid)?.let { serverErrors(it.fieldErrors) }.orEmpty()
                state.update {
                    when {
                        result is ApiResult.Success -> it.copy(suggestion = SuggestionSheet.Ready(result.data))
                        // Something the AI needs is refused: the sheet closes and the field says what.
                        errors.isNotEmpty() -> it.copy(suggestion = null).withErrors(errors)
                        else -> it.copy(suggestion = result.suggestionFailure())
                    }
                }
            }
    }

    /** Fills the description and summary; over a written description, only the second tap does. */
    private fun useSuggestion() {
        val current = state.value
        val sheet = current.suggestion as? SuggestionSheet.Ready ?: return
        if (current.description.isNotBlank() && !sheet.confirmReplace) {
            state.update { it.copy(suggestion = sheet.copy(confirmReplace = true)) }
            return
        }
        edit {
            it
                .withText(ProductTextField.Description, sheet.suggestion.description)
                .withText(ProductTextField.SeoDescription, sheet.suggestion.seoDescription)
                .copy(suggestion = null)
        }
    }
}
