package io.github.saptooaji.seafood.seller.feature.stock.data

import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST

/** The daily stock endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface StockApi {
    @GET("seller/stock/today")
    suspend fun today(): Response<DataEnvelope<List<StockProductDto>>>

    @POST("seller/stock/confirm")
    suspend fun confirm(
        @Body request: ConfirmStockRequest,
    ): Response<DataEnvelope<List<StockProductDto>>>
}
