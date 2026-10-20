package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.auth.data.DataEnvelope
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryRepository
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.Json
import retrofit2.Response
import retrofit2.http.GET

interface CategoryApi {
    @GET("categories")
    suspend fun tree(): Response<DataEnvelope<List<CategoryDto>>>
}

/** `CategoryResource`: a node with its effective attributes and its children. */
@Serializable
data class CategoryDto(
    val id: Long,
    val name: String,
    val attributes: List<AttributeDto> = emptyList(),
    val children: List<CategoryDto> = emptyList(),
) {
    fun toDomain(): Category = Category(id, name, attributes.map { it.toDomain() }, children.map { it.toDomain() })
}

/** `CategoryAttributeResource`, and the attribute part of `ProductResource.attributes`. */
@Serializable
data class AttributeDto(
    val id: Long,
    val name: String,
    val type: TypeDto,
    val options: List<String>? = null,
    val unit: String? = null,
    @SerialName("is_required") val isRequired: Boolean = false,
    /** Set in a product's attributes only. */
    val value: String? = null,
) {
    fun toDomain() =
        CategoryAttribute(
            id = id,
            name = name,
            type = AttributeType.from(type.value),
            options = options.orEmpty(),
            unit = unit,
            isRequired = isRequired,
        )
}

@Serializable
data class TypeDto(
    val value: String,
    val label: String? = null,
)

/**
 * The tree changes rarely (admins edit it), so a successful answer is kept
 * for the session; a failure is not, so the next call tries again.
 */
class CategoryRepositoryImpl(
    private val api: CategoryApi,
    private val json: Json,
) : CategoryRepository {
    private val mutex = Mutex()
    private var cached: List<Category>? = null

    override suspend fun tree(): ApiResult<List<Category>> =
        mutex.withLock {
            cached?.let { return@withLock ApiResult.Success(it) }
            val result = apiCall(json) { api.tree() }.map { envelope -> envelope.data.map { it.toDomain() } }
            if (result is ApiResult.Success) cached = result.data
            result
        }
}
