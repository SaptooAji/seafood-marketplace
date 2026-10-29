package io.github.saptooaji.seafood.seller.feature.stock.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.material3.DatePicker
import androidx.compose.material3.DatePickerDialog
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.material3.rememberDatePickerState
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.unit.dp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import java.time.Instant
import java.time.LocalDate
import java.time.ZoneOffset

/**
 * A day for the stock book's filter, picked from the date picker; "Hapus"
 * in the picker takes it away. [refused] edges it red.
 */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
internal fun DateField(
    label: String,
    value: LocalDate?,
    tag: String,
    modifier: Modifier = Modifier,
    refused: Boolean = false,
    onPick: (LocalDate?) -> Unit,
) {
    var picking by rememberSaveable { mutableStateOf(false) }
    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text(text = label, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 52.dp)
                    .background(EmbunColors.Field, OptionShape)
                    .border(1.5.dp, if (refused) EmbunColors.DangerContent else EmbunColors.FieldEdge, OptionShape)
                    .clickable(role = Role.Button) { picking = true }
                    .testTag(tag)
                    .padding(horizontal = 14.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Text(
                text = value?.let(::fieldDate) ?: stringResource(R.string.stock_filter_any_date),
                color = if (value == null) EmbunColors.Muted else MaterialTheme.colorScheme.onSurface,
                modifier = Modifier.weight(1f),
            )
            Icon(
                painterResource(R.drawable.ic_calendar),
                contentDescription = null,
                tint = EmbunColors.Muted,
                modifier = Modifier.size(18.dp),
            )
        }
    }

    if (picking) {
        // The picker counts in UTC midnights.
        val state = rememberDatePickerState(initialSelectedDateMillis = value?.atStartOfDay(ZoneOffset.UTC)?.toInstant()?.toEpochMilli())
        DatePickerDialog(
            onDismissRequest = { picking = false },
            confirmButton = {
                TextButton(
                    onClick = {
                        onPick(state.selectedDateMillis?.let { Instant.ofEpochMilli(it).atZone(ZoneOffset.UTC).toLocalDate() })
                        picking = false
                    },
                ) { Text(stringResource(R.string.product_form_date_ok)) }
            },
            dismissButton = {
                TextButton(
                    onClick = {
                        onPick(null)
                        picking = false
                    },
                ) { Text(stringResource(R.string.stock_filter_clear_date)) }
            },
        ) {
            DatePicker(state = state)
        }
    }
}
