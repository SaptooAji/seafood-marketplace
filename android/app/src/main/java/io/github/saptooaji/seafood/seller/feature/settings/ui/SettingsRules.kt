package io.github.saptooaji.seafood.seller.feature.settings.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.PhoneNumber
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import java.time.LocalTime
import java.time.format.DateTimeFormatter

/** The longest name `UpdateProfileRequest` takes. */
private const val NAME_MAX = 255

/** [value] typed into [field], whose error clears. */
fun ProfileFormState.with(
    field: ProfileField,
    value: String,
): ProfileFormState {
    val changed =
        when (field) {
            ProfileField.Name -> copy(name = value)
            ProfileField.Phone -> copy(phone = value)
        }
    return changed.copy(errors = errors - field, generalError = null)
}

/**
 * The rules of `UpdateProfileRequest`, checked before sending: the form with
 * its errors, and the trimmed name and normalised phone when there is none.
 */
fun ProfileFormState.validated(): Pair<ProfileFormState, Pair<String, String>?> {
    val trimmed = name.trim()
    val normalized = PhoneNumber.normalize(phone)
    val errors =
        buildMap {
            when {
                trimmed.isEmpty() -> put(ProfileField.Name, UiText.Resource(R.string.register_error_name_required))
                trimmed.codePointCount(0, trimmed.length) > NAME_MAX ->
                    put(ProfileField.Name, UiText.Resource(R.string.register_error_name_too_long))
            }
            if (!PhoneNumber.isValid(normalized)) put(ProfileField.Phone, UiText.Resource(R.string.register_error_phone_invalid))
        }
    return copy(errors = errors) to (trimmed to normalized).takeIf { errors.isEmpty() }
}

/** The fields a 422 names, with the app's own text; null when it names neither. */
fun ProfileFormState.withServerErrors(fieldErrors: Map<String, List<String>>): ProfileFormState? {
    val errors =
        buildMap {
            if ("name" in fieldErrors) put(ProfileField.Name, UiText.Resource(R.string.register_error_name_required))
            if ("phone" in fieldErrors) put(ProfileField.Phone, UiText.Resource(R.string.register_error_phone_invalid))
        }
    return if (errors.isEmpty()) null else copy(errors = this.errors + errors)
}

/**
 * Every field of [this] store with [stockReminderTime], for `PUT seller/store`,
 * which takes them all. Null when the store lacks what the request needs,
 * such as the bank account staff never get.
 */
fun Store.toDetails(stockReminderTime: LocalTime?): StoreDetails? {
    val account = bank
    val latitude = lat
    val longitude = lng
    return if (account == null || latitude == null || longitude == null) {
        null
    } else {
        StoreDetails(
            name = name,
            description = description,
            phone = phone,
            address = address,
            city = city,
            lat = latitude,
            lng = longitude,
            bankName = account.name,
            bankAccountNo = account.accountNo,
            bankAccountName = account.accountName,
            stockReminderTime = stockReminderTime,
        )
    }
}

/** The reminder field of the sheet: two boxes, hours and minutes, on a 24-hour clock. */
object ReminderTime {
    private val DIGITS = Regex("^\\d{1,2}$")
    private val SHOWN: DateTimeFormatter = DateTimeFormatter.ofPattern("HH.mm")
    private const val HOURS = 24
    private const val MINUTES = 60

    /** The time typed, or null when it is not on the clock. */
    fun parse(
        hour: String,
        minute: String,
    ): LocalTime? {
        val h = hour.trim()
        val m = minute.trim()
        val valid = DIGITS.matches(h) && DIGITS.matches(m) && h.toInt() < HOURS && m.toInt() < MINUTES
        return if (valid) LocalTime.of(h.toInt(), m.toInt()) else null
    }

    /** "06.00": Indonesian times are written with a dot. */
    fun format(time: LocalTime): String = time.format(SHOWN)
}

/** What the rows of Pengaturan say. */
object SettingsLabels {
    private const val SHOWN_DIGITS = 4

    /** "BCA •••2233, Budi Santoso": the whole number never shows on the tab. */
    fun bankSummary(bank: BankAccount): String = "${bank.name} •••${bank.accountNo.takeLast(SHOWN_DIGITS)}, ${bank.accountName}"

    /** The first letter of the name, for a user without an avatar. */
    fun initial(name: String): String {
        val trimmed = name.trim()
        return if (trimmed.isEmpty()) "?" else trimmed.substring(0, trimmed.offsetByCodePoints(0, 1)).uppercase()
    }
}
