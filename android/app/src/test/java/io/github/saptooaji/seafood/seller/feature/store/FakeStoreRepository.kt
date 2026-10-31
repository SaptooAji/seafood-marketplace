package io.github.saptooaji.seafood.seller.feature.store

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.CurrentStore
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.coroutines.CompletableDeferred
import java.math.BigDecimal
import java.time.LocalTime

/** A [StoreRepository] whose answers each test sets, recording every call. */
class FakeStoreRepository : StoreRepository {
    val openCalls = mutableListOf<StoreDetails>()
    var openResult: ApiResult<Store> = ApiResult.Success(PENDING_STORE)

    /** When set, open waits for it, so a test can look at the state mid-request. */
    var openGate: CompletableDeferred<Unit>? = null

    var currentCalls = 0

    /** Answers to `current()`, one per call; the last one repeats. */
    val currentResults = ArrayDeque<ApiResult<CurrentStore>>(listOf(ApiResult.Success(CurrentStore.Exists(ACTIVE_STORE))))

    /** When set, current waits for it, so a test can look at the state mid-request. */
    var currentGate: CompletableDeferred<Unit>? = null

    override suspend fun current(): ApiResult<CurrentStore> {
        currentCalls++
        currentGate?.await()
        return if (currentResults.size > 1) currentResults.removeFirst() else currentResults.first()
    }

    /** Sets the answers of the next `current()` calls, in order. */
    fun answerCurrent(vararg results: ApiResult<CurrentStore>) {
        currentResults.clear()
        currentResults.addAll(results)
    }

    override suspend fun open(details: StoreDetails): ApiResult<Store> {
        openCalls += details
        openGate?.await()
        return openResult
    }

    val updateCalls = mutableListOf<StoreDetails>()

    /** The answer to `update()`; by default the store as sent, so a test can read it back. */
    var updateResult: ApiResult<Store>? = null

    /** When set, update waits for it, so a test can look at the state mid-request. */
    var updateGate: CompletableDeferred<Unit>? = null

    override suspend fun update(details: StoreDetails): ApiResult<Store> {
        updateCalls += details
        updateGate?.await()
        return updateResult ?: ApiResult.Success(ACTIVE_STORE.withDetails(details))
    }

    companion object {
        val PENDING_STORE =
            Store(
                id = 3,
                name = "UD Kepiting Pak Budi",
                slug = "ud-kepiting-pak-budi",
                description = null,
                phone = "081234567890",
                address = "Jl. Dermaga Ujung No. 12, Muara Angke",
                city = "Jakarta Utara",
                lat = BigDecimal("-6.1067000"),
                lng = BigDecimal("106.7740000"),
                status = StoreStatus.Pending,
                statusLabel = "Menunggu Persetujuan",
                rejectionReason = null,
                bank = BankAccount(name = "BCA", accountNo = "7700112233", accountName = "Budi Santoso"),
                role = StoreRole.Owner,
                stockReminderTime = null,
            )

        val ACTIVE_STORE = PENDING_STORE.copy(status = StoreStatus.Active, statusLabel = "Aktif")
        val REJECTED_STORE =
            PENDING_STORE.copy(status = StoreStatus.Rejected, statusLabel = "Ditolak", rejectionReason = "Foto KTP buram.", bank = null)
        val SUSPENDED_STORE = PENDING_STORE.copy(status = StoreStatus.Suspended, statusLabel = "Ditangguhkan")

        /** An active store with a 06.00 reminder, as its owner sees it. */
        val OWNED_STORE = ACTIVE_STORE.copy(stockReminderTime = LocalTime.of(6, 0))

        /** The same store as a staff member sees it: no bank account (`StoreResource`). */
        val STAFF_STORE = OWNED_STORE.copy(role = StoreRole.Staff, bank = null)

        /** [this] store with what [details] changed, as `PUT seller/store` answers. */
        fun Store.withDetails(details: StoreDetails) =
            copy(
                name = details.name,
                description = details.description,
                phone = details.phone,
                address = details.address,
                city = details.city,
                lat = details.lat,
                lng = details.lng,
                bank = BankAccount(details.bankName, details.bankAccountNo, details.bankAccountName),
                stockReminderTime = details.stockReminderTime,
            )

        fun found(store: Store): ApiResult<CurrentStore> = ApiResult.Success(CurrentStore.Exists(store))

        val NO_STORE: ApiResult<CurrentStore> = ApiResult.Success(CurrentStore.NoStore)
    }
}
