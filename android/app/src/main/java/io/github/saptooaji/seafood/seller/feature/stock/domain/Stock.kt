package io.github.saptooaji.seafood.seller.feature.stock.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import java.math.BigDecimal
import java.time.Instant

/** An active product on "Stok hari ini" with its active variants to count (#243). */
data class StockProduct(
    val id: Long,
    val name: String,
    val variants: List<StockVariant>,
)

/**
 * One variant to count. [confirmedAt] is null when it was never counted;
 * [isStale] is the server's own judgement that the count is too old.
 */
data class StockVariant(
    val id: Long,
    val name: String,
    val unit: VariantUnit,
    /** The server's label for the unit, such as `Kg` or `Pcs`. */
    val unitLabel: String,
    val stock: BigDecimal,
    val confirmedAt: Instant?,
    val isStale: Boolean,
)

/** A counted stock that differs from the server's. */
data class StockCount(
    val variantId: Long,
    val stock: BigDecimal,
)

/** The daily stock check (`ConfirmDailyStock`, #94). */
interface StockRepository {
    /** `GET seller/stock/today`: products by name, each with its active variants. */
    suspend fun today(): ApiResult<List<StockProduct>>

    /**
     * `POST seller/stock/confirm`: sets [counts] and confirms every other
     * variant as it is ("Sama seperti kemarin"). Answers with the screen as
     * it is afterwards. A refused count comes back keyed by its index in
     * [counts], such as `variants.0.stock`.
     */
    suspend fun confirm(counts: List<StockCount>): ApiResult<List<StockProduct>>
}
