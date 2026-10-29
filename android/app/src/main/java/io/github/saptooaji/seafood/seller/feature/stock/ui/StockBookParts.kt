package io.github.saptooaji.seafood.seller.feature.stock.ui

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

/** The filter sheet, part of [StockBookViewModel]: a draft until it is [apply]'d. */
internal class FilterEditor(
    private val state: MutableStateFlow<StockBookUiState>,
    private val apply: (StockFilter) -> Unit,
) {
    /** True when [event] was the filter's. */
    fun handle(event: StockBookEvent): Boolean {
        when (event) {
            StockBookEvent.OpenFilter -> state.update { it.copy(filterSheet = FilterDraft.of(it.filter)) }
            StockBookEvent.CloseFilter -> state.update { it.copy(filterSheet = null) }
            is StockBookEvent.FilterTypePicked -> draft { it.copy(type = event.type) }
            is StockBookEvent.FilterVariantPicked -> draft { it.copy(variantId = event.variantId) }
            is StockBookEvent.FilterDatesChanged -> draft { it.copy(from = event.from, to = event.to) }
            StockBookEvent.ApplyFilter -> applyDraft()
            StockBookEvent.ClearFilter -> {
                state.update { it.copy(filterSheet = null) }
                apply(StockFilter())
            }
            else -> return false
        }
        return true
    }

    /** A draft with an end before its start stays open with its error. */
    private fun applyDraft() {
        val draft = state.value.filterSheet
        if (draft != null && draft.error == null) {
            state.update { it.copy(filterSheet = null) }
            apply(draft.toFilter())
        }
    }

    private fun draft(change: (FilterDraft) -> FilterDraft) {
        state.update { current -> current.filterSheet?.let { current.copy(filterSheet = change(it)) } ?: current }
    }
}

/**
 * The record sheet, part of [StockBookViewModel]. Checks what is typed the
 * way the server would before sending it; [onSaved] hears each change the
 * server took.
 */
internal class StockRecorder(
    private val stock: StockRepository,
    private val state: MutableStateFlow<StockBookUiState>,
    private val scope: CoroutineScope,
    private val onSaved: () -> Unit,
) {
    /** True when [event] was the sheet's. */
    fun handle(event: StockBookEvent): Boolean {
        when (event) {
            StockBookEvent.OpenRecord -> state.update { it.copy(record = RecordSheet()) }
            StockBookEvent.CloseRecord -> state.update { it.copy(record = null) }
            is StockBookEvent.RecordTypePicked ->
                sheet {
                    it.copy(
                        type = event.type,
                        errors = it.errors - RecordField.Qty,
                        saveError = null,
                    )
                }
            StockBookEvent.OpenVariantPicker -> sheet { it.copy(pickingVariant = true) }
            StockBookEvent.CloseVariantPicker -> sheet { it.copy(pickingVariant = false) }
            is StockBookEvent.RecordVariantPicked -> pick(event.variantId)
            is StockBookEvent.RecordTextChanged -> sheet { it.withText(event.field, event.text) }
            StockBookEvent.SaveRecord -> save()
            else -> return false
        }
        return true
    }

    private fun sheet(change: (RecordSheet) -> RecordSheet) {
        state.update { current -> current.record?.let { current.copy(record = change(it)) } ?: current }
    }

    /** Only a variant from Stok hari ini can be picked, so no id is ever typed. */
    private fun pick(id: Long) {
        val variant = state.value.variant(id) ?: return
        sheet { it.copy(variantId = id, variant = variant, pickingVariant = false, errors = it.errors - RecordField.Variant) }
    }

    /** Checks the sheet; only a change the server would take is sent. */
    private fun save() {
        val sheet = state.value.record
        if (sheet == null || sheet.isSaving) return
        val (errors, movement) = sheet.validated()
        if (movement == null) sheet { it.copy(errors = errors) } else send(movement)
    }

    private fun send(movement: NewStockMovement) {
        sheet { it.copy(isSaving = true, errors = emptyMap(), saveError = null) }
        scope.launch {
            when (val result = stock.record(movement)) {
                is ApiResult.Success -> {
                    state.update { it.copy(record = null) }
                    onSaved()
                }
                is ApiResult.Invalid -> sheet { it.refusedBy(result) }
                else -> sheet { it.copy(isSaving = false, saveError = recordSaveMessage(result)) }
            }
        }
    }
}
