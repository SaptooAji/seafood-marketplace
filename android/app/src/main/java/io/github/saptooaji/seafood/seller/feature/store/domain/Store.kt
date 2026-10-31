package io.github.saptooaji.seafood.seller.feature.store.domain

import java.math.BigDecimal
import java.time.LocalTime

/** A store as its members see it (`StoreResource`). */
data class Store(
    val id: Long,
    val name: String,
    val slug: String,
    val description: String?,
    val phone: String,
    val address: String,
    val city: String,
    val lat: BigDecimal?,
    val lng: BigDecimal?,
    val status: StoreStatus,
    /** The server's Indonesian label for [status], shown as is. */
    val statusLabel: String,
    /** Only a rejected store has one. */
    val rejectionReason: String?,
    /** Shown to the owner only; null for staff. */
    val bank: BankAccount?,
    /** The role of the signed-in user in this store; it decides who may edit, not [bank]. */
    val role: StoreRole? = null,
    /** When the morning stock reminder goes off (Tahap 6); null when none is set. */
    val stockReminderTime: LocalTime? = null,
)

/** `StoreRole` on the server: only the owner edits the store (`StorePolicy::update`). */
enum class StoreRole {
    Owner,
    Staff,

    /** A role added on the server after this app version. */
    Unknown,
    ;

    companion object {
        fun from(value: String): StoreRole =
            when (value) {
                "owner" -> Owner
                "staff" -> Staff
                else -> Unknown
            }
    }
}

/** `StoreStatus` on the server. A new store waits for an admin as [Pending]. */
enum class StoreStatus {
    Pending,
    Active,
    Rejected,
    Suspended,

    /** A status added on the server after this app version. */
    Unknown,
    ;

    companion object {
        fun from(value: String): StoreStatus =
            when (value) {
                "pending" -> Pending
                "active" -> Active
                "rejected" -> Rejected
                "suspended" -> Suspended
                else -> Unknown
            }
    }
}

/** Where buyers' payments go. Never stored on the device or logged. */
data class BankAccount(
    val name: String,
    val accountNo: String,
    val accountName: String,
) {
    override fun toString() = "BankAccount(name=$name, accountNo=██, accountName=██)"
}
