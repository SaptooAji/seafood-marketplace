package io.github.saptooaji.seafood.seller.feature.product.data

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.core.network.apiCall
import io.github.saptooaji.seafood.seller.core.network.map
import io.github.saptooaji.seafood.seller.feature.product.domain.DescriptionSuggestion
import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDetail
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantChanges
import kotlinx.serialization.json.Json

class ProductRepositoryImpl(
    private val api: ProductApi,
    private val json: Json,
) : ProductRepository {
    override suspend fun list(
        status: ProductStatusFilter,
        search: String?,
        page: Int,
    ): ApiResult<ProductPage> = apiCall(json) { api.list(status.query, search, page) }.map { it.toDomain() }

    override suspend fun get(id: Long): ApiResult<ProductDetail> = apiCall(json) { api.get(id) }.map { it.data.toDomain() }

    override suspend fun create(
        draft: ProductDraft,
        variant: NewVariant,
    ): ApiResult<ProductDetail> = apiCall(json) { api.create(ProductRequest.from(draft, variant)) }.map { it.data.toDomain() }

    override suspend fun update(
        id: Long,
        draft: ProductDraft,
    ): ApiResult<ProductDetail> = apiCall(json) { api.update(id, ProductRequest.from(draft, variant = null)) }.map { it.data.toDomain() }

    override suspend fun suggestDescription(draft: ProductDraft): ApiResult<DescriptionSuggestion> =
        apiCall(json) { api.suggestDescription(ProductRequest.forSuggestion(draft)) }.map { it.data.toDomain() }

    override suspend fun delete(id: Long): ApiResult<Unit> = apiCall(json) { api.delete(id) }

    override suspend fun addVariant(
        productId: Long,
        variant: NewVariant,
    ): ApiResult<ProductVariant> = apiCall(json) { api.addVariant(productId, NewVariantRequest.from(variant)) }.map { it.data.toDomain() }

    override suspend fun updateVariant(
        id: Long,
        changes: VariantChanges,
    ): ApiResult<ProductVariant> = apiCall(json) { api.updateVariant(id, VariantChangesRequest.from(changes)) }.map { it.data.toDomain() }

    override suspend fun deleteVariant(id: Long): ApiResult<Unit> = apiCall(json) { api.deleteVariant(id) }
}
