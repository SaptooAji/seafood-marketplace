package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ReadTimeout
import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
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

    @GET("seller/products/{id}")
    suspend fun get(
        @Path("id") id: Long,
    ): Response<DataEnvelope<ProductDetailDto>>

    @POST("seller/products")
    suspend fun create(
        @Body request: ProductRequest,
    ): Response<DataEnvelope<ProductDetailDto>>

    @PUT("seller/products/{id}")
    suspend fun update(
        @Path("id") id: Long,
        @Body request: ProductRequest,
    ): Response<DataEnvelope<ProductDetailDto>>

    /** Gemini can take a while, so this one waits longer than the client's default. */
    @ReadTimeout(SUGGESTION_TIMEOUT_SECONDS)
    @POST("seller/products/description-suggestion")
    suspend fun suggestDescription(
        @Body request: ProductRequest,
    ): Response<DataEnvelope<DescriptionSuggestionDto>>

    @DELETE("seller/products/{id}")
    suspend fun delete(
        @Path("id") id: Long,
    ): Response<Unit>

    @POST("seller/products/{id}/variants")
    suspend fun addVariant(
        @Path("id") productId: Long,
        @Body request: NewVariantRequest,
    ): Response<DataEnvelope<VariantDto>>

    @PUT("seller/variants/{id}")
    suspend fun updateVariant(
        @Path("id") id: Long,
        @Body request: VariantChangesRequest,
    ): Response<DataEnvelope<VariantDto>>

    @DELETE("seller/variants/{id}")
    suspend fun deleteVariant(
        @Path("id") id: Long,
    ): Response<Unit>
}

/** How long the AI description may take: the server may try two Gemini models, 20 seconds each. */
const val SUGGESTION_TIMEOUT_SECONDS = 45
