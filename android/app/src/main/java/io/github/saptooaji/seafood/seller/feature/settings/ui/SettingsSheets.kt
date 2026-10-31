package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.BasicTextField
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.feature.product.ui.DangerPillButton
import io.github.saptooaji.seafood.seller.feature.product.ui.EmbunSheet
import io.github.saptooaji.seafood.seller.feature.product.ui.SheetHeading
import java.time.LocalTime

/** The board VD-Ubah-Profil and its state with a refused phone. */
@Composable
internal fun BoxScope.ProfileSheet(
    profile: ProfileFormState,
    email: String,
    onEvent: (SettingsEvent) -> Unit,
) {
    EmbunSheet(onDismiss = { onEvent(SettingsEvent.CloseProfile) }) {
        SheetTop(stringResource(R.string.settings_profile_title), SettingsTags.PROFILE_CLOSE) { onEvent(SettingsEvent.CloseProfile) }
        GlassTextField(
            value = profile.name,
            onValueChange = { onEvent(SettingsEvent.ProfileChanged(ProfileField.Name, it)) },
            label = stringResource(R.string.settings_profile_name),
            error = profile.errors[ProfileField.Name]?.asString(),
            contentType = ContentType.PersonFullName,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
            modifier = Modifier.testTag(SettingsTags.PROFILE_NAME),
        )
        GlassTextField(
            value = profile.phone,
            onValueChange = { onEvent(SettingsEvent.ProfileChanged(ProfileField.Phone, it)) },
            label = stringResource(R.string.settings_profile_phone),
            error = profile.errors[ProfileField.Phone]?.asString(),
            helpText = stringResource(R.string.settings_profile_phone_help),
            contentType = ContentType.PhoneNumber,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Done),
            modifier = Modifier.testTag(SettingsTags.PROFILE_PHONE),
        )
        GlassTextField(
            value = email,
            onValueChange = {},
            label = stringResource(R.string.settings_profile_email),
            helpText = stringResource(R.string.settings_profile_email_help),
            enabled = false,
            modifier = Modifier.testTag(SettingsTags.PROFILE_EMAIL),
        )
        profile.generalError?.let { GlassBanner(text = it.asString(), tone = BannerTone.Danger) }
        PrimaryPillButton(
            text = stringResource(if (profile.isSaving) R.string.stock_saving else R.string.settings_save),
            onClick = { onEvent(SettingsEvent.SaveProfile) },
            enabled = !profile.isSaving,
            modifier = Modifier.testTag(SettingsTags.PROFILE_SAVE),
        )
    }
}

/**
 * The board VD-Pengingat-Stok: hours and minutes on a 24-hour clock. The
 * time typed lives in the sheet until it is saved; [initial] is the time set
 * now, and removing appears only when there is one.
 */
@Composable
internal fun BoxScope.ReminderSheet(
    initial: LocalTime?,
    onSave: (LocalTime?) -> Unit,
    onDismiss: () -> Unit,
) {
    val start = initial ?: DEFAULT_REMINDER
    var hour by rememberSaveable { mutableStateOf(twoDigits(start.hour)) }
    var minute by rememberSaveable { mutableStateOf(twoDigits(start.minute)) }
    val time = ReminderTime.parse(hour, minute)

    EmbunSheet(onDismiss = onDismiss) {
        SheetTop(stringResource(R.string.settings_reminder), SettingsTags.REMINDER_CLOSE, onDismiss)
        Text(
            text = stringResource(R.string.settings_reminder_help),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 15.sp),
            color = EmbunColors.Muted,
        )
        Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Bottom) {
            TimeBox(stringResource(R.string.settings_reminder_hour), hour, SettingsTags.REMINDER_HOUR, Modifier.weight(1f)) { hour = it }
            Text(
                text = ":",
                style = MaterialTheme.typography.displaySmall.copy(fontWeight = FontWeight.Bold),
                modifier = Modifier.padding(bottom = 18.dp),
            )
            TimeBox(stringResource(R.string.settings_reminder_minute), minute, SettingsTags.REMINDER_MINUTE, Modifier.weight(1f)) {
                minute =
                    it
            }
        }
        Text(
            text = stringResource(R.string.settings_reminder_format),
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
            color = if (time == null) EmbunColors.DangerContent else EmbunColors.Muted,
            modifier = Modifier.padding(start = 4.dp),
        )
        PrimaryPillButton(
            text = stringResource(R.string.settings_save),
            onClick = { onSave(time) },
            enabled = time != null,
            modifier = Modifier.testTag(SettingsTags.REMINDER_SAVE),
        )
        if (initial != null) {
            DangerPillButton(
                text = stringResource(R.string.settings_reminder_remove),
                onClick = { onSave(null) },
                modifier = Modifier.testTag(SettingsTags.REMINDER_REMOVE),
            )
        }
    }
}

/** One box of the reminder: two big digits typed on the number keyboard. */
@Composable
private fun TimeBox(
    label: String,
    value: String,
    tag: String,
    modifier: Modifier = Modifier,
    onValueChange: (String) -> Unit,
) {
    Column(modifier, verticalArrangement = Arrangement.spacedBy(8.dp), horizontalAlignment = Alignment.CenterHorizontally) {
        Text(text = label, style = MaterialTheme.typography.labelMedium, color = EmbunColors.Muted)
        BasicTextField(
            value = value,
            onValueChange = { if (it.length <= 2) onValueChange(it) },
            singleLine = true,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.NumberPassword, imeAction = ImeAction.Next),
            textStyle =
                MaterialTheme.typography.displayMedium.copy(
                    fontSize = 52.sp,
                    fontWeight = FontWeight.Bold,
                    letterSpacing = (-0.03).em,
                    textAlign = TextAlign.Center,
                    color = MaterialTheme.colorScheme.onSurface,
                ),
            cursorBrush = SolidColor(MaterialTheme.colorScheme.primary),
            modifier =
                Modifier
                    .fillMaxWidth()
                    .height(96.dp)
                    .background(EmbunColors.Field, TimeBoxShape)
                    .border(1.5.dp, EmbunColors.FieldEdge, TimeBoxShape)
                    .padding(vertical = 14.dp)
                    .testTag(tag),
        )
    }
}

/** The board VD-Pengaturan--konfirmasi-keluar. */
@Composable
internal fun BoxScope.SignOutSheet(onEvent: (SettingsEvent) -> Unit) {
    EmbunSheet(onDismiss = { onEvent(SettingsEvent.CancelSignOut) }) {
        SheetHeading(
            title = stringResource(R.string.settings_sign_out_title),
            body = stringResource(R.string.settings_sign_out_body),
        )
        DangerPillButton(
            text = stringResource(R.string.settings_sign_out),
            onClick = { onEvent(SettingsEvent.SignOut) },
            filled = true,
            modifier = Modifier.testTag(SettingsTags.SIGN_OUT_CONFIRM),
        )
        GlassPillButton(
            text = stringResource(R.string.settings_sign_out_cancel),
            onClick = { onEvent(SettingsEvent.CancelSignOut) },
            modifier = Modifier.testTag(SettingsTags.SIGN_OUT_CANCEL),
        )
    }
}

@Composable
private fun SheetTop(
    title: String,
    closeTag: String,
    onClose: () -> Unit,
) {
    Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        SheetHeading(title = title, modifier = Modifier.weight(1f))
        Surface(
            onClick = onClose,
            shape = CircleShape,
            color = EmbunColors.GlassStrong,
            modifier = Modifier.size(44.dp).testTag(closeTag),
        ) {
            Box(contentAlignment = Alignment.Center) {
                Icon(
                    painterResource(R.drawable.ic_close),
                    contentDescription = stringResource(R.string.settings_close),
                    modifier = Modifier.size(22.dp),
                )
            }
        }
    }
}

private fun twoDigits(value: Int) = value.toString().padStart(2, '0')

/** Where the sheet starts when no reminder is set: early, before buyers order. */
private val DEFAULT_REMINDER: LocalTime = LocalTime.of(6, 0)
private val TimeBoxShape = RoundedCornerShape(22.dp)
