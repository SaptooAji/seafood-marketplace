package io.github.saptooaji.seafood.seller.feature.store

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreDetails
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRepository
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.coroutines.CompletableDeferred
import java.math.BigDecimal

/** A [StoreRepository] whose answers each test sets, recording every call. */
class FakeStoreRepository : StoreRepository {
    val openCalls = mutableListOf<StoreDetails>()
    var openResult: ApiResult<Store> = ApiResult.Success(PENDING_STORE)

    /** When set, open waits for it, so a test can look at the state mid-request. */
    var openGate: CompletableDeferred<Unit>? = null

    override suspend fun open(details: StoreDetails): ApiResult<Store> {
        openCalls += details
        openGate?.await()
        return openResult
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
            )
    }
}
