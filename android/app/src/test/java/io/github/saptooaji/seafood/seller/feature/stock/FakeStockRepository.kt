package io.github.saptooaji.seafood.seller.feature.stock

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.stock.domain.NewStockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockCount
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockFilter
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovement
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementPage
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockMovementType
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockProduct
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockRepository
import io.github.saptooaji.seafood.seller.feature.stock.domain.StockVariant
import java.math.BigDecimal
import java.time.Instant

/**
 * Answers with [products] until told otherwise; every confirm is kept in
 * [confirmed], every stock book request in [movementCalls], and every new
 * entry in [recorded].
 */
class FakeStockRepository : StockRepository {
    var products: List<StockProduct> = SCREEN
    var todayResult: ApiResult<List<StockProduct>>? = null

    /** Null answers with [products], as the server returns the screen after confirming. */
    var confirmResult: ApiResult<List<StockProduct>>? = null
    val confirmed = mutableListOf<List<StockCount>>()
    var todayCalls = 0

    override suspend fun today(): ApiResult<List<StockProduct>> {
        todayCalls++
        return todayResult ?: ApiResult.Success(products)
    }

    override suspend fun confirm(counts: List<StockCount>): ApiResult<List<StockProduct>> {
        confirmed += counts
        return confirmResult ?: ApiResult.Success(products)
    }

    data class MovementCall(
        val filter: StockFilter,
        val page: Int,
    )

    val movementCalls = mutableListOf<MovementCall>()

    /** Pages of [movements], 30 each, unless [movementsResult] says otherwise. */
    var movements: List<StockMovement> = BOOK
    var movementsResult: ApiResult<StockMovementPage>? = null

    val recorded = mutableListOf<NewStockMovement>()
    var recordResult: ApiResult<StockMovement>? = null

    override suspend fun movements(
        filter: StockFilter,
        page: Int,
    ): ApiResult<StockMovementPage> {
        movementCalls += MovementCall(filter, page)
        movementsResult?.let { return it }
        val lastPage = maxOf(1, (movements.size + PAGE_SIZE - 1) / PAGE_SIZE)
        val slice = movements.drop((page - 1) * PAGE_SIZE).take(PAGE_SIZE)
        return ApiResult.Success(StockMovementPage(slice, page, lastPage, movements.size))
    }

    override suspend fun record(movement: NewStockMovement): ApiResult<StockMovement> {
        recorded += movement
        return recordResult ?: ApiResult.Success(BOOK.first())
    }

    companion object {
        /** 05.40 WIB on Sunday 18 October 2026. */
        val TODAY_0540: Instant = Instant.parse("2026-10-17T22:40:00Z")

        /** 06.15 WIB on Saturday 17 October. */
        val YESTERDAY_0615: Instant = Instant.parse("2026-10-16T23:15:00Z")

        /** 06.20 WIB on Thursday 15 October, three days before. */
        val THREE_DAYS_AGO: Instant = Instant.parse("2026-10-14T23:20:00Z")

        fun variant(
            id: Long,
            name: String,
            stock: String,
            unit: VariantUnit = VariantUnit.Kg,
            confirmedAt: Instant? = TODAY_0540,
        ) = StockVariant(
            id = id,
            name = name,
            unit = unit,
            unitLabel = if (unit == VariantUnit.Kg) "Kg" else "Pcs",
            stock = BigDecimal(stock),
            confirmedAt = confirmedAt,
            // As the server judges it here: only a variant never counted is stale.
            isStale = confirmedAt == null,
        )

        /** Ids 11-12 and 21 are kg, 31 is per piece; Rajungan Super is out of stock. */
        val KEPITING_300 = variant(11, "300–400 g per ekor", "12.000")
        val KEPITING_400 = variant(12, "400–500 g per ekor", "6.500")
        val RAJUNGAN_SUPER = variant(21, "Super", "0.000", confirmedAt = null)
        val KAKAP_UTUH = variant(31, "Utuh 1 kg", "2.000", VariantUnit.Piece, confirmedAt = YESTERDAY_0615)

        const val PAGE_SIZE = 30

        /** 05.10 WIB on Sunday 18 October 2026. */
        val TODAY_0510: Instant = Instant.parse("2026-10-17T22:10:00Z")

        fun movement(
            id: Long,
            type: StockMovementType = StockMovementType.StockIn,
            qty: String = "5.000",
            after: String = "12.500",
            createdAt: Instant = TODAY_0510,
        ) = StockMovement(
            id = id,
            type = type,
            typeLabel = type.name,
            qty = BigDecimal(qty),
            stockAfter = BigDecimal(after),
            variantId = KEPITING_300.id,
            variantName = KEPITING_300.name,
            unitLabel = "Kg",
            productName = "Kepiting Bakau Super",
            supplierName = null,
            unitCost = null,
            note = null,
            createdBy = "Budi",
            createdAt = createdAt,
        )

        val BOOK = listOf(movement(1))

        val SCREEN =
            listOf(
                StockProduct(7, "Kepiting Bakau Super", listOf(KEPITING_300, KEPITING_400)),
                StockProduct(8, "Rajungan", listOf(RAJUNGAN_SUPER)),
                StockProduct(9, "Kakap Merah", listOf(KAKAP_UTUH)),
            )
    }
}
