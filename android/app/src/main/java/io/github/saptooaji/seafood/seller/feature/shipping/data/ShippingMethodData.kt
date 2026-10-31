package io.github.saptooaji.seafood.seller.feature.shipping.data

import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodInput
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethodRepository
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import io.github.saptooaji.seafood.seller.feature.store.data.StatusDto
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import retrofit2.Response
import retrofit2.Retrofit
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.POST
import retrofit2.http.PUT
import retrofit2.http.Path
import javax.inject.Singleton

/** The shipping method endpoints of `/api/v1` (docs/prd.md, "Endpoint API"). */
interface ShippingMethodApi {
    @GET("seller/shipping-methods")
    suspend fun list(): Response<DataEnvelope<List<ShippingMethodDto>>>

    @POST("seller/shipping-methods")
    suspend fun add(
        @Body request: ShippingMethodRequest,
    ): Response<DataEnvelope<ShippingMethodDto>>

    @PUT("seller/shipping-methods/{id}")
    suspend fun update(
        @Path("id") id: Long,
        @Body request: ShippingMethodRequest,
    ): Response<DataEnvelope<ShippingMethodDto>>
}

/**
 * The body of `POST` and `PUT`. Null fields are left out by the shared Json,
 * so a fee the fee type does not use is never sent. The radius goes as a
 * decimal string, so its two decimals reach the server without float rounding.
 */
@Serializable
data class ShippingMethodRequest(
    val type: String? = null,
    @SerialName("fee_type") val feeType: String,
    @SerialName("max_radius_km") val maxRadiusKm: String? = null,
    @SerialName("flat_fee") val flatFee: Long? = null,
    @SerialName("fee_per_km") val feePerKm: Long? = null,
    @SerialName("min_fee") val minFee: Long? = null,
    @SerialName("is_active") val isActive: Boolean? = null,
) {
    companion object {
        fun from(
            input: ShippingMethodInput,
            withType: Boolean,
        ) = ShippingMethodRequest(
            type = input.type.value.takeIf { withType },
            feeType = input.feeType.value,
            maxRadiusKm = input.maxRadiusKm?.toPlainString(),
            flatFee = input.flatFee,
            feePerKm = input.feePerKm,
            minFee = input.minFee,
            isActive = input.isActive,
        )
    }
}

/** `ShippingMethodResource`. The radius arrives as a string such as `"20.00"`. */
@Serializable
data class ShippingMethodDto(
    val id: Long,
    val type: StatusDto,
    @SerialName("fee_type") val feeType: StatusDto,
    @SerialName("max_radius_km") val maxRadiusKm: String? = null,
    @SerialName("flat_fee") val flatFee: Long? = null,
    @SerialName("fee_per_km") val feePerKm: Long? = null,
    @SerialName("min_fee") val minFee: Long? = null,
    @SerialName("is_active") val isActive: Boolean,
) {
    /** Null for a kind or fee type this app does not know yet. */
    fun toDomain(): ShippingMethod? {
        val kind = ShippingType.from(type.value)
        val fee = ShippingFeeType.from(feeType.value)
        return if (kind == null || fee == null) {
            null
        } else {
            ShippingMethod(
                id = id,
                type = kind,
                feeType = fee,
                maxRadiusKm = maxRadiusKm?.toBigDecimalOrNull(),
                flatFee = flatFee,
                feePerKm = feePerKm,
                minFee = minFee,
                isActive = isActive,
            )
        }
    }
}

class ShippingMethodRepositoryImpl(
    private val api: ShippingMethodApi,
    private val json: Json,
) : ShippingMethodRepository {
    override suspend fun list(): ApiResult<List<ShippingMethod>> =
        apiCall(json) { api.list() }.map { envelope -> envelope.data.mapNotNull { it.toDomain() } }

    override suspend fun add(input: ShippingMethodInput): ApiResult<ShippingMethod> =
        apiCall(json) { api.add(ShippingMethodRequest.from(input, withType = true)) }.toMethod()

    override suspend fun update(
        id: Long,
        input: ShippingMethodInput,
    ): ApiResult<ShippingMethod> = apiCall(json) { api.update(id, ShippingMethodRequest.from(input, withType = false)) }.toMethod()

    /** The method the server saved; a kind this app cannot read counts as an unreadable answer. */
    private fun ApiResult<DataEnvelope<ShippingMethodDto>>.toMethod(): ApiResult<ShippingMethod> {
        val read = map { it.data.toDomain() }
        val method = (read as? ApiResult.Success)?.data
        return when {
            method != null -> ApiResult.Success(method)
            read is ApiResult.Success -> ApiResult.ServerError
            // A failure: map passed it through, so it holds no data to cast.
            else -> read.map { checkNotNull(it) }
        }
    }
}

@Module
@InstallIn(SingletonComponent::class)
object ShippingModule {
    @Provides
    @Singleton
    fun shippingMethodApi(retrofit: Retrofit): ShippingMethodApi = retrofit.create(ShippingMethodApi::class.java)

    @Provides
    @Singleton
    fun shippingMethodRepository(
        api: ShippingMethodApi,
        json: Json,
    ): ShippingMethodRepository = ShippingMethodRepositoryImpl(api, json)
}
