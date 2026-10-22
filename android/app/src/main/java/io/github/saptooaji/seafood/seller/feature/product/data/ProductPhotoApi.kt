package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ReadTimeout
import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import okhttp3.MultipartBody
import retrofit2.Response
import retrofit2.http.Body
import retrofit2.http.DELETE
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Part
import retrofit2.http.Path

/** The photo endpoints of a seller's product (#241, docs/prd.md "Endpoint API"). */
interface ProductPhotoApi {
    /** A prepared JPEG as the multipart part `photo`; uploads on a slow connection may take a while. */
    @ReadTimeout(UPLOAD_TIMEOUT_SECONDS)
    @Multipart
    @POST("seller/products/{id}/images")
    suspend fun uploadImage(
        @Path("id") productId: Long,
        @Part photo: MultipartBody.Part,
    ): Response<DataEnvelope<ImageDto>>

    @PUT("seller/products/{id}/images/order")
    suspend fun reorderImages(
        @Path("id") productId: Long,
        @Body request: ImageOrderRequest,
    ): Response<DataEnvelope<List<ImageDto>>>

    @DELETE("seller/products/{id}/images/{imageId}")
    suspend fun deleteImage(
        @Path("id") productId: Long,
        @Path("imageId") imageId: Long,
    ): Response<Unit>
}

/** How long one photo upload may take to be answered, after its last byte was sent. */
const val UPLOAD_TIMEOUT_SECONDS = 60
