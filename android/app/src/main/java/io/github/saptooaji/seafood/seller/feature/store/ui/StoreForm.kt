package io.github.saptooaji.seafood.seller.feature.store.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.PhoneNumber
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails

/** The parts of the store form that can be wrong; [Pin] is the map. */
enum class StoreField {
    Name,
    Description,
    Phone,
    Address,
    City,
    Pin,
    BankName,
    BankAccountNo,
    BankAccountName,
}

/**
 * The store form as typed, shared by opening a store and editing it (#245).
 * [pin] is the point under the map's pin, null until the map reports one.
 */
data class StoreFormState(
    val name: String = "",
    val description: String = "",
    val phone: String = "",
    val address: String = "",
    val city: String = "",
    val pin: GeoPoint? = null,
    val bankName: String = "",
    val bankAccountNo: String = "",
    val bankAccountName: String = "",
    val errors: Map<StoreField, UiText> = emptyMap(),
) {
    fun value(field: StoreField): String =
        when (field) {
            StoreField.Name -> name
            StoreField.Description -> description
            StoreField.Phone -> phone
            StoreField.Address -> address
            StoreField.City -> city
            StoreField.Pin -> ""
            StoreField.BankName -> bankName
            StoreField.BankAccountNo -> bankAccountNo
            StoreField.BankAccountName -> bankAccountName
        }

    /** [value] typed into [field], whose error clears. */
    fun with(
        field: StoreField,
        value: String,
    ): StoreFormState {
        val changed =
            when (field) {
                StoreField.Name -> copy(name = value)
                StoreField.Description -> copy(description = value)
                StoreField.Phone -> copy(phone = value)
                StoreField.Address -> copy(address = value)
                StoreField.City -> copy(city = value)
                StoreField.Pin -> this
                StoreField.BankName -> copy(bankName = value)
                StoreField.BankAccountNo -> copy(bankAccountNo = value)
                StoreField.BankAccountName -> copy(bankAccountName = value)
            }
        return changed.copy(errors = errors - field)
    }

    fun withPin(point: GeoPoint) = copy(pin = point, errors = errors - StoreField.Pin)

    // Text is trimmed and the phone loses its spaces and dashes; the account
    // number is sent as typed, so a space in it is refused, not removed.
    private val details: StoreDetails?
        get() {
            val point = pin ?: return null
            return StoreDetails(
                name = name.trim(),
                description = description.trim().ifEmpty { null },
                phone = PhoneNumber.normalize(phone),
                address = address.trim(),
                city = city.trim(),
                lat = point.scaledLat,
                lng = point.scaledLng,
                bankName = bankName.trim(),
                bankAccountNo = bankAccountNo,
                bankAccountName = bankAccountName.trim(),
            )
        }

    /**
     * The same rules as `StoreRequest`, checked before sending so the errors
     * show at once: the form with every error filled in, and the details to
     * send when there is none. The server stays the final judge.
     */
    fun validated(): Pair<StoreFormState, StoreDetails?> {
        val errors =
            buildMap {
                text(StoreField.Name, name.trim(), StoreLimits.NAME, R.string.store_error_name_required, R.string.store_error_name_too_long)
                if (description.trim().characters() > StoreLimits.DESCRIPTION) {
                    put(StoreField.Description, UiText.Resource(R.string.store_error_description_too_long))
                }
                if (!PhoneNumber.isValid(PhoneNumber.normalize(phone))) {
                    put(StoreField.Phone, UiText.Resource(R.string.register_error_phone_invalid))
                }
                text(
                    StoreField.Address,
                    address.trim(),
                    StoreLimits.ADDRESS,
                    R.string.store_error_address_required,
                    R.string.store_error_address_too_long,
                )
                text(StoreField.City, city.trim(), StoreLimits.CITY, R.string.store_error_city_required, R.string.store_error_city_too_long)
                if (pin == null) put(StoreField.Pin, UiText.Resource(R.string.store_error_pin_required))
                text(
                    StoreField.BankName,
                    bankName.trim(),
                    StoreLimits.BANK_NAME,
                    R.string.store_error_bank_name_required,
                    R.string.store_error_bank_name_too_long,
                )
                if (!StoreLimits.ACCOUNT_NO.matches(bankAccountNo)) {
                    put(StoreField.BankAccountNo, UiText.Resource(R.string.store_error_bank_account_no_invalid))
                }
                text(
                    StoreField.BankAccountName,
                    bankAccountName.trim(),
                    StoreLimits.ACCOUNT_NAME,
                    R.string.store_error_bank_account_name_required,
                    R.string.store_error_bank_account_name_too_long,
                )
            }
        return copy(errors = errors) to details.takeIf { errors.isEmpty() }
    }

    /**
     * The fields a 422 names, each with the app's own text: the server's
     * messages follow its locale. Null when the 422 names no field of the form.
     */
    fun withServerErrors(fieldErrors: Map<String, List<String>>): StoreFormState? {
        val errors = fieldErrors.keys.mapNotNull { SERVER_FIELDS[it] }.toMap()
        return if (errors.isEmpty()) null else copy(errors = this.errors + errors)
    }

    private fun MutableMap<StoreField, UiText>.text(
        field: StoreField,
        trimmed: String,
        max: Int,
        required: Int,
        tooLong: Int,
    ) {
        when {
            trimmed.isEmpty() -> put(field, UiText.Resource(required))
            trimmed.characters() > max -> put(field, UiText.Resource(tooLong))
        }
    }

    private companion object {
        /** Laravel's `max` counts characters, not UTF-16 units. */
        fun String.characters() = codePointCount(0, length)

        /** The server's field keys, with the message the app shows for each. */
        val SERVER_FIELDS: Map<String, Pair<StoreField, UiText>> =
            mapOf(
                "name" to (StoreField.Name to UiText.Resource(R.string.store_error_name_required)),
                "description" to (StoreField.Description to UiText.Resource(R.string.store_error_description_too_long)),
                "phone" to (StoreField.Phone to UiText.Resource(R.string.register_error_phone_invalid)),
                "address" to (StoreField.Address to UiText.Resource(R.string.store_error_address_required)),
                "city" to (StoreField.City to UiText.Resource(R.string.store_error_city_required)),
                "lat" to (StoreField.Pin to UiText.Resource(R.string.store_error_pin_required)),
                "lng" to (StoreField.Pin to UiText.Resource(R.string.store_error_pin_required)),
                "bank_name" to (StoreField.BankName to UiText.Resource(R.string.store_error_bank_name_required)),
                "bank_account_no" to (StoreField.BankAccountNo to UiText.Resource(R.string.store_error_bank_account_no_invalid)),
                "bank_account_name" to (StoreField.BankAccountName to UiText.Resource(R.string.store_error_bank_account_name_required)),
            )
    }
}

/** The limits of `StoreRequest` on the server. */
object StoreLimits {
    const val NAME = 100
    const val DESCRIPTION = 2000
    const val ADDRESS = 500
    const val CITY = 100
    const val BANK_NAME = 100
    const val ACCOUNT_NAME = 255
    val ACCOUNT_NO = Regex("^\\d{5,20}$")
}
