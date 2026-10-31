package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus

/** The store panel of Pengaturan: its name, status, and rows. */
@Composable
internal fun StoreCard(
    uiState: SettingsUiState,
    store: Store,
    onEditStore: () -> Unit,
    onShippingMethods: () -> Unit,
    onOpenReminder: () -> Unit,
) {
    val owner = uiState.isOwner
    GlassCard(Modifier.fillMaxWidth(), spacing = 10.dp) {
        Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text(text = store.name, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp))
                Text(
                    text = storeLine(store),
                    style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                    color = EmbunColors.Muted,
                )
            }
            StatusLabel(store)
        }
        Column {
            if (owner) {
                SettingRow(
                    icon = R.drawable.ic_store_pin,
                    title = stringResource(R.string.settings_store_profile),
                    body = stringResource(R.string.settings_store_profile_body),
                    tag = SettingsTags.EDIT_STORE,
                    onClick = onEditStore,
                )
                store.bank?.let { bank ->
                    SettingRow(
                        icon = R.drawable.ic_bank,
                        title = stringResource(R.string.settings_bank),
                        body = SettingsLabels.bankSummary(bank),
                        tag = SettingsTags.BANK,
                        onClick = onEditStore,
                    )
                }
            }
            SettingRow(
                icon = R.drawable.ic_truck,
                title = stringResource(R.string.settings_shipping),
                body = stringResource(R.string.settings_shipping_body),
                tag = SettingsTags.SHIPPING,
                onClick = onShippingMethods,
            )
            SettingRow(
                icon = R.drawable.ic_status_clock,
                title = stringResource(R.string.settings_reminder),
                body = stringResource(R.string.settings_reminder_body),
                tag = SettingsTags.REMINDER,
                onClick = onOpenReminder,
                enabled = owner && !uiState.isSavingReminder,
                trailing = { ReminderValue(store) },
            )
        }
        if (!owner) {
            Text(
                text = stringResource(R.string.settings_owner_only_hint),
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
            )
        }
    }
}

@Composable
private fun storeLine(store: Store): String =
    when (store.role) {
        StoreRole.Owner -> stringResource(R.string.settings_store_owner, store.city)
        StoreRole.Staff -> stringResource(R.string.settings_store_staff, store.city)
        else -> store.city
    }

/** The server's label, green when the store sells, sand while waiting, red otherwise. */
@Composable
private fun StatusLabel(store: Store) {
    val (ground, ink) =
        when (store.status) {
            StoreStatus.Active -> ActiveGround to ActiveInk
            StoreStatus.Pending -> EmbunColors.WarnBackground to EmbunColors.WarnContent
            else -> EmbunColors.DangerBackground to EmbunColors.DangerContent
        }
    Text(
        text = store.statusLabel,
        style = MaterialTheme.typography.labelSmall.copy(fontSize = 12.sp, fontWeight = FontWeight.Bold),
        color = ink,
        maxLines = 1,
        modifier = Modifier.background(ground, PillShape).padding(horizontal = 10.dp, vertical = 3.dp),
    )
}

@Composable
private fun ReminderValue(store: Store) {
    val time = store.stockReminderTime
    if (time != null) {
        Text(
            text = ReminderTime.format(time),
            style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp),
        )
    } else {
        Text(
            text = stringResource(R.string.settings_reminder_unset),
            style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp),
            color = EmbunColors.Muted,
        )
    }
}

/** One row of the store panel: a line above, an icon, two lines of text, and a chevron. */
@Composable
private fun SettingRow(
    @DrawableRes icon: Int,
    title: String,
    body: String,
    tag: String,
    onClick: () -> Unit,
    enabled: Boolean = true,
    trailing: @Composable () -> Unit = { Chevron() },
) {
    Column {
        Box(Modifier.fillMaxWidth().height(1.dp).background(EmbunColors.Line))
        Row(
            modifier =
                Modifier
                    .fillMaxWidth()
                    .heightIn(min = 64.dp)
                    .testTag(tag)
                    .clickable(enabled = enabled, role = Role.Button, onClick = onClick)
                    .alpha(if (enabled) 1f else DISABLED_ALPHA)
                    .padding(vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(14.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Box(Modifier.size(40.dp).background(EmbunColors.GlassButton, CircleShape), contentAlignment = Alignment.Center) {
                Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(20.dp))
            }
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(2.dp)) {
                Text(text = title, style = MaterialTheme.typography.titleSmall.copy(fontWeight = FontWeight.Bold, fontSize = 15.sp))
                Text(text = body, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp), color = EmbunColors.Muted)
            }
            trailing()
        }
    }
}

@Composable
private fun Chevron() {
    Icon(painterResource(R.drawable.ic_chevron_right), contentDescription = null, tint = EmbunColors.Muted, modifier = Modifier.size(20.dp))
}

private const val DISABLED_ALPHA = 0.6f
private val ActiveGround = Color(0xFFE3EEE7)
private val ActiveInk = Color(0xFF244A38)
