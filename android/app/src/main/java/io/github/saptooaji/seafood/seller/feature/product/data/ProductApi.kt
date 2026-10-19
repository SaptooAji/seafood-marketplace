package io.github.saptooaji.seafood.seller.feature.product.data

import retrofit2.Response
import retrofit2.http.GET
import retrofit2.http.Query

/** The seller's product endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface ProductApi {
    /** Null parameters are left out of the URL. */
    @GET("seller/products")
    suspend fun list(
        @Query("status") status: String?,
        @Query("search") search: String?,
        @Query("page") page: Int,
    ): Response<ProductPageDto>
}
