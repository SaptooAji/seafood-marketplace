package io.github.saptooaji.seafood.seller.feature.shipping

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import kotlinx.coroutines.CompletableDeferred
import java.math.BigDecimal

/** A [ShippingMethodRepository] whose answers each test sets, recording every call. */
class FakeShippingMethodRepository : ShippingMethodRepository {
    var listCalls = 0

    /** Answers to `list()`, one per call; the last one repeats. */
    private val listResults = ArrayDeque<ApiResult<List<ShippingMethod>>>(listOf(ApiResult.Success(DEFAULT)))

    fun answerList(vararg results: ApiResult<List<ShippingMethod>>) {
        listResults.clear()
        listResults.addAll(results)
    }

    override suspend fun list(): ApiResult<List<ShippingMethod>> {
        listCalls++
        return if (listResults.size > 1) listResults.removeFirst() else listResults.first()
    }

    val addCalls = mutableListOf<ShippingMethodInput>()

    /** The answer to `add()`; by default the method as sent, with a new id. */
    var addResult: ApiResult<ShippingMethod>? = null
    var addGate: CompletableDeferred<Unit>? = null

    override suspend fun add(input: ShippingMethodInput): ApiResult<ShippingMethod> {
        addCalls += input
        addGate?.await()
        return addResult ?: ApiResult.Success(input.saved(id = NEW_ID))
    }

    val updateCalls = mutableListOf<Pair<Long, ShippingMethodInput>>()

    /** Answers to `update()`, one per call; when empty, the method as sent. */
    val updateResults = ArrayDeque<ApiResult<ShippingMethod>>()

    /** Gates for `update()`, one per call, so a test can hold each request. */
    val updateGates = ArrayDeque<CompletableDeferred<Unit>>()

    override suspend fun update(
        id: Long,
        input: ShippingMethodInput,
    ): ApiResult<ShippingMethod> {
        updateCalls += id to input
        updateGates.removeFirstOrNull()?.await()
        return updateResults.removeFirstOrNull() ?: ApiResult.Success(input.saved(id))
    }

    companion object {
        const val NEW_ID = 9L

        val PICKUP =
            ShippingMethod(id = 1, type = ShippingType.Pickup, feeType = ShippingFeeType.Flat, flatFee = 0, isActive = true)

        val OWN_PER_KM =
            ShippingMethod(
                id = 2,
                type = ShippingType.OwnDelivery,
                feeType = ShippingFeeType.PerKm,
                maxRadiusKm = BigDecimal("20.00"),
                feePerKm = 3_000,
                minFee = 10_000,
                isActive = true,
            )

        val OWN_FLAT =
            OWN_PER_KM.copy(
                feeType = ShippingFeeType.Flat,
                maxRadiusKm = BigDecimal("10.00"),
                flatFee = 10_000,
                feePerKm = null,
                minFee = null,
            )

        val OJOL = ShippingMethod(id = 3, type = ShippingType.Ojol, feeType = ShippingFeeType.Manual, isActive = true)

        /** A store as the board VD-Metode-Pengiriman draws it: pickup and own delivery, no ojol or expedition. */
        val DEFAULT = listOf(PICKUP, OWN_PER_KM)

        private fun ShippingMethodInput.saved(id: Long) =
            ShippingMethod(
                id = id,
                type = type,
                feeType = feeType,
                maxRadiusKm = maxRadiusKm,
                flatFee = flatFee,
                feePerKm = feePerKm,
                minFee = minFee,
                isActive = isActive ?: true,
            )
    }
}
