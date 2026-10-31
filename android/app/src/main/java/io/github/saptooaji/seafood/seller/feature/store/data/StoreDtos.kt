package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonNull
import kotlinx.serialization.json.JsonPrimitive
import java.time.LocalTime
import java.time.format.DateTimeFormatter

/**
 * The body of `POST` and `PUT seller/store`. Coordinates go as decimal
 * strings, so the seven decimals reach the server without float rounding.
 * The reminder is a JSON element so that "no reminder" goes as an explicit
 * `null`: the shared [kotlinx.serialization.json.Json] leaves Kotlin nulls
 * out, and a missing key would keep the old time on the server.
 */
@Serializable
data class StoreRequest(
    val name: String,
    val description: String?,
    val phone: String,
    val address: String,
    val city: String,
    val lat: String,
    val lng: String,
    @SerialName("bank_name") val bankName: String,
    @SerialName("bank_account_no") val bankAccountNo: String,
    @SerialName("bank_account_name") val bankAccountName: String,
    // No default: the shared Json does not encode defaults, and the key must always go.
    @SerialName("stock_reminder_time") val stockReminderTime: JsonElement,
) {
    override fun toString() = "StoreRequest(name=$name, city=$city, lat=$lat, lng=$lng, bankAccountNo=██)"

    companion object {
        fun from(details: StoreDetails) =
            with(details) {
                StoreRequest(
                    name = name,
                    description = description,
                    phone = phone,
                    address = address,
                    city = city,
                    lat = lat.toPlainString(),
                    lng = lng.toPlainString(),
                    bankName = bankName,
                    bankAccountNo = bankAccountNo,
                    bankAccountName = bankAccountName,
                    stockReminderTime = stockReminderTime?.let { JsonPrimitive(it.format(REMINDER_FORMAT)) } ?: JsonNull,
                )
            }
    }
}

/** `H:i` on the server: two-digit hours and minutes, such as `06:30`. */
private val REMINDER_FORMAT: DateTimeFormatter = DateTimeFormatter.ofPattern("HH:mm")

/** `StoreResource`. Decimal columns arrive as strings, such as `"-6.1067000"`. */
@Serializable
data class StoreDto(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String? = null,
    val phone: String,
    val address: String,
    val city: String,
    val lat: String? = null,
    val lng: String? = null,
    val status: StatusDto,
    @SerialName("rejection_reason") val rejectionReason: String? = null,
    val bank: BankDto? = null,
    val role: StatusDto? = null,
    @SerialName("stock_reminder_time") val stockReminderTime: String? = null,
) {
    fun toDomain() =
        Store(
            id = id,
            name = name,
            slug = slug,
            description = description,
            phone = phone,
            address = address,
            city = city,
            lat = lat?.toBigDecimalOrNull(),
            lng = lng?.toBigDecimalOrNull(),
            status = StoreStatus.from(status.value),
            statusLabel = status.label,
            rejectionReason = rejectionReason,
            bank = bank?.let { BankAccount(it.name, it.accountNo, it.accountName) },
            role = role?.let { StoreRole.from(it.value) },
            stockReminderTime = stockReminderTime?.let { runCatching { LocalTime.parse(it, REMINDER_FORMAT) }.getOrNull() },
        )
}

/** An enum as the API sends it: `{"value": "pending", "label": "Menunggu Persetujuan"}`. */
@Serializable
data class StatusDto(
    val value: String,
    val label: String,
)

@Serializable
data class BankDto(
    val name: String,
    @SerialName("account_no") val accountNo: String,
    @SerialName("account_name") val accountName: String,
) {
    override fun toString() = "BankDto(name=$name, accountNo=██)"
}
