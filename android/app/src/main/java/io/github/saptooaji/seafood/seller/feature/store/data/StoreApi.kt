package io.github.saptooaji.seafood.seller.feature.store.data

import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT

/** The seller's store endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface StoreApi {
    @GET("seller/store")
    suspend fun current(): Response<DataEnvelope<StoreDto>>

    @POST("seller/store")
    suspend fun open(
        @Body request: StoreRequest,
    ): Response<DataEnvelope<StoreDto>>

    @PUT("seller/store")
    suspend fun update(
        @Body request: StoreRequest,
    ): Response<DataEnvelope<StoreDto>>
}
