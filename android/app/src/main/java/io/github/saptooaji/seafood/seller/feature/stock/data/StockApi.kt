package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.Query

/** The daily stock endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface StockApi {
    @GET("seller/stock/today")
    suspend fun today(): Response<DataEnvelope<List<StockProductDto>>>

    @POST("seller/stock/confirm")
    suspend fun confirm(
        @Body request: ConfirmStockRequest,
    ): Response<DataEnvelope<List<StockProductDto>>>

    /** Null parameters are left out of the URL. */
    @GET("seller/stock-movements")
    suspend fun movements(
        @Query("variant_id") variantId: Long?,
        @Query("type") type: String?,
        @Query("from") from: String?,
        @Query("to") to: String?,
        @Query("page") page: Int,
    ): Response<StockMovementPageDto>

    @POST("seller/stock-movements")
    suspend fun record(
        @Body request: StockMovementRequest,
    ): Response<DataEnvelope<StockMovementDto>>
}
