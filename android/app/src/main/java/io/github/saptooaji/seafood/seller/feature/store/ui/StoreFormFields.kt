package io.github.saptooaji.seafood.seller.feature.store.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassSectionHeader
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.theme.OptionShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.map.MapTiles
import java.math.BigDecimal

/**
 * The three parts of the store form, as the board VD-Buka-Toko draws them:
 * the profile, the location, and the bank account. Shared by opening a store
 * and editing it (#245).
 *
 * @param map draws the map itself filling the given modifier; the pin, the
 *   location button, and the attribution are drawn over it here
 */
@Composable
fun StoreFormFields(
    form: StoreFormState,
    onFieldChange: (StoreField, String) -> Unit,
    onUseMyLocation: () -> Unit,
    locationMessage: UiText?,
    map: @Composable (Modifier) -> Unit,
    modifier: Modifier = Modifier,
    numbered: Boolean = true,
) {
    Column(modifier, verticalArrangement = Arrangement.spacedBy(14.dp)) {
        ProfileSection(form, onFieldChange, numbered)
        LocationSection(form, onUseMyLocation, locationMessage, map, numbered)
        BankSection(form, onFieldChange, numbered)
    }
}

@Composable
private fun ProfileSection(
    form: StoreFormState,
    onFieldChange: (StoreField, String) -> Unit,
    numbered: Boolean,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(
            number = 1.takeIf { numbered },
            // Editing, the screen title already says "Profil toko" (VD-Profil-Toko).
            title = stringResource(if (numbered) R.string.store_section_profile else R.string.store_section_profile_short),
        )
        StoreTextField(
            form,
            StoreField.Name,
            onFieldChange,
            label = R.string.store_name_label,
            tag = StoreFormTags.NAME,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
        )
        StoreTextField(
            form,
            StoreField.Description,
            onFieldChange,
            label = R.string.store_description_label,
            tag = StoreFormTags.DESCRIPTION,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Sentences),
            minLines = 3,
        )
        StoreTextField(
            form,
            StoreField.Phone,
            onFieldChange,
            label = R.string.store_phone_label,
            tag = StoreFormTags.PHONE,
            contentType = ContentType.PhoneNumber,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Next),
        )
        StoreTextField(
            form,
            StoreField.Address,
            onFieldChange,
            label = R.string.store_address_label,
            tag = StoreFormTags.ADDRESS,
            contentType = ContentType.AddressStreet,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words),
            minLines = 2,
        )
        StoreTextField(
            form,
            StoreField.City,
            onFieldChange,
            label = R.string.store_city_label,
            tag = StoreFormTags.CITY,
            contentType = ContentType.AddressLocality,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
        )
    }
}

@Composable
private fun LocationSection(
    form: StoreFormState,
    onUseMyLocation: () -> Unit,
    locationMessage: UiText?,
    map: @Composable (Modifier) -> Unit,
    numbered: Boolean,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 2.takeIf { numbered }, title = stringResource(R.string.store_section_location))
        Text(text = stringResource(R.string.store_location_help), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.Muted)

        val mapDescription = stringResource(R.string.store_map_description)
        Box(
            Modifier
                .fillMaxWidth()
                .height(260.dp)
                .clip(OptionShape)
                .background(MapLand)
                .semantics { contentDescription = mapDescription },
        ) {
            map(Modifier.matchParentSize())
            // The tip of the pin (y 58 of 60) sits on the centre of the map.
            Image(
                painter = painterResource(R.drawable.ic_store_pin),
                contentDescription = null,
                modifier =
                    Modifier
                        .align(Alignment.Center)
                        .offset(y = (-28).dp)
                        .testTag(StoreFormTags.PIN),
            )
            MyLocationButton(onUseMyLocation, Modifier.align(Alignment.BottomStart).padding(10.dp))
            Text(
                text = stringResource(R.string.store_map_attribution),
                style = MaterialTheme.typography.labelSmall.copy(fontSize = 11.sp, fontWeight = FontWeight.Normal),
                color = AttributionText,
                modifier =
                    Modifier
                        .align(Alignment.BottomEnd)
                        .padding(8.dp)
                        .background(Color.White.copy(alpha = 0.85f), AttributionShape)
                        .padding(horizontal = 6.dp, vertical = 2.dp),
            )
        }

        val pin = form.pin
        Text(
            text =
                if (pin != null) {
                    stringResource(R.string.store_pin_coordinates, pin.scaledLat.indonesian(), pin.scaledLng.indonesian())
                } else {
                    stringResource(R.string.store_pin_unset)
                },
            style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
            color = EmbunColors.Muted,
        )
        form.errors[StoreField.Pin]?.let { error ->
            Text(text = error.asString(), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.DangerContent)
        }
        locationMessage?.let { message ->
            Text(text = message.asString(), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.WarnContent)
        }
    }
}

@Composable
private fun MyLocationButton(
    onClick: () -> Unit,
    modifier: Modifier = Modifier,
) {
    Surface(
        onClick = onClick,
        shape = PillShape,
        color = Color.White,
        contentColor = MaterialTheme.colorScheme.onSurface,
        modifier = modifier.heightIn(min = 40.dp).testTag(StoreFormTags.MY_LOCATION),
    ) {
        Row(
            modifier = Modifier.padding(horizontal = 14.dp, vertical = 10.dp),
            horizontalArrangement = Arrangement.spacedBy(6.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            Icon(painter = painterResource(R.drawable.ic_my_location), contentDescription = null, modifier = Modifier.size(18.dp))
            Text(text = stringResource(R.string.store_my_location), style = MaterialTheme.typography.labelLarge)
        }
    }
}

@Composable
private fun BankSection(
    form: StoreFormState,
    onFieldChange: (StoreField, String) -> Unit,
    numbered: Boolean,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        GlassSectionHeader(number = 3.takeIf { numbered }, title = stringResource(R.string.store_section_bank))
        Text(text = stringResource(R.string.store_bank_help), style = MaterialTheme.typography.bodyMedium, color = EmbunColors.Muted)
        StoreTextField(
            form,
            StoreField.BankName,
            onFieldChange,
            label = R.string.store_bank_name_label,
            tag = StoreFormTags.BANK_NAME,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Characters, imeAction = ImeAction.Next),
        )
        StoreTextField(
            form,
            StoreField.BankAccountNo,
            onFieldChange,
            label = R.string.store_bank_account_no_label,
            tag = StoreFormTags.BANK_ACCOUNT_NO,
            helpText = R.string.store_bank_account_no_help,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number, imeAction = ImeAction.Next, autoCorrectEnabled = false),
        )
        StoreTextField(
            form,
            StoreField.BankAccountName,
            onFieldChange,
            label = R.string.store_bank_account_name_label,
            tag = StoreFormTags.BANK_ACCOUNT_NAME,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Done),
        )
    }
}

@Suppress("LongParameterList") // Every field of the form differs in these few settings only.
@Composable
private fun StoreTextField(
    form: StoreFormState,
    field: StoreField,
    onFieldChange: (StoreField, String) -> Unit,
    label: Int,
    tag: String,
    keyboardOptions: KeyboardOptions,
    contentType: ContentType? = null,
    helpText: Int? = null,
    minLines: Int = 1,
) {
    GlassTextField(
        value = form.value(field),
        onValueChange = { onFieldChange(field, it) },
        label = stringResource(label),
        error = form.errors[field]?.asString(),
        helpText = helpText?.let { stringResource(it) },
        contentType = contentType,
        keyboardOptions = keyboardOptions,
        singleLine = minLines == 1,
        minLines = minLines,
        modifier = Modifier.testTag(tag),
    )
}

/** "-6.1067000" as Indonesian writes it: "−6,1067000", with a true minus sign. */
private fun BigDecimal.indonesian(): String = toPlainString().replace('.', ',').replace('-', '−')

/** The map's land colour behind the tiles: [MapTiles.BACKGROUND], the style's background. */
private val MapLand = Color(0xFFECE8DD)
private val AttributionText = Color(0xFF575C52)
private val AttributionShape = RoundedCornerShape(6.dp)
