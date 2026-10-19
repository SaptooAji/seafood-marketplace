package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

/**
 * The body of `POST seller/store`. Coordinates go as decimal strings, so the
 * seven decimals reach the server without float rounding.
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
                )
            }
    }
}

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
