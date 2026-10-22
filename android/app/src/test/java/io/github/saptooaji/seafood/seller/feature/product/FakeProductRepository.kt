package io.github.saptooaji.seafood.seller.feature.product

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.NewVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDetail
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductDraft
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantChanges
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import kotlinx.coroutines.CompletableDeferred
import java.math.BigDecimal

/** A [ProductRepository] whose answers each test sets, recording every call. */
class FakeProductRepository : ProductRepository {
    data class Call(
        val status: ProductStatusFilter,
        val search: String?,
        val page: Int,
    )

    val calls = mutableListOf<Call>()

    /** Answers by page number; a page with no answer gets an empty last page. */
    val answers = mutableMapOf<Int, ApiResult<ProductPage>>()

    /** When set for a page, the request for it waits until the gate completes. */
    val gates = mutableMapOf<Int, CompletableDeferred<Unit>>()

    override suspend fun list(
        status: ProductStatusFilter,
        search: String?,
        page: Int,
    ): ApiResult<ProductPage> {
        calls += Call(status, search, page)
        gates[page]?.await()
        return answers[page] ?: ApiResult.Success(ProductPage(emptyList(), currentPage = page, lastPage = page, total = 0))
    }

    val getCalls = mutableListOf<Long>()
    var getResult: ApiResult<ProductDetail> = ApiResult.Success(DETAIL)

    val createCalls = mutableListOf<Pair<ProductDraft, NewVariant>>()
    var createResult: ApiResult<ProductDetail> = ApiResult.Success(DETAIL)

    val updateCalls = mutableListOf<Pair<Long, ProductDraft>>()
    var updateResult: ApiResult<ProductDetail> = ApiResult.Success(DETAIL)

    val deleteCalls = mutableListOf<Long>()
    var deleteResult: ApiResult<Unit> = ApiResult.Success(Unit)

    /** When set, saving waits for it, so a test can look at the state mid-request. */
    var saveGate: CompletableDeferred<Unit>? = null

    override suspend fun get(id: Long): ApiResult<ProductDetail> {
        getCalls += id
        return getResult
    }

    override suspend fun create(
        draft: ProductDraft,
        variant: NewVariant,
    ): ApiResult<ProductDetail> {
        createCalls += draft to variant
        saveGate?.await()
        return createResult
    }

    override suspend fun update(
        id: Long,
        draft: ProductDraft,
    ): ApiResult<ProductDetail> {
        updateCalls += id to draft
        saveGate?.await()
        return updateResult
    }

    override suspend fun delete(id: Long): ApiResult<Unit> {
        deleteCalls += id
        return deleteResult
    }

    val addVariantCalls = mutableListOf<Pair<Long, NewVariant>>()

    /** Null answers with the variant as sent, under a new id. */
    var addVariantResult: ApiResult<ProductVariant>? = null

    val updateVariantCalls = mutableListOf<Pair<Long, VariantChanges>>()

    /** Null answers with the variant as sent. */
    var updateVariantResult: ApiResult<ProductVariant>? = null

    val deleteVariantCalls = mutableListOf<Long>()
    var deleteVariantResult: ApiResult<Unit> = ApiResult.Success(Unit)

    /** By variant id, or [NEW_VARIANT] for adding: the call waits until the gate completes. */
    val variantGates = mutableMapOf<Long, CompletableDeferred<Unit>>()

    override suspend fun addVariant(
        productId: Long,
        variant: NewVariant,
    ): ApiResult<ProductVariant> {
        addVariantCalls += productId to variant
        variantGates[NEW_VARIANT]?.await()
        return addVariantResult ?: ApiResult.Success(
            ProductVariant(
                id = 100L + addVariantCalls.size,
                name = variant.name,
                unitLabel = variant.unit.value,
                price = variant.price,
                stock = variant.initialStock ?: BigDecimal.ZERO,
                isStockStale = false,
                isActive = true,
                unit = variant.unit,
                minQty = variant.minQty,
            ),
        )
    }

    override suspend fun updateVariant(
        id: Long,
        changes: VariantChanges,
    ): ApiResult<ProductVariant> {
        updateVariantCalls += id to changes
        variantGates[id]?.await()
        return updateVariantResult ?: ApiResult.Success(
            variant(id = id, price = changes.price, isActive = changes.isActive, name = changes.name, unit = changes.unit)
                .copy(minQty = changes.minQty),
        )
    }

    override suspend fun deleteVariant(id: Long): ApiResult<Unit> {
        deleteVariantCalls += id
        variantGates[id]?.await()
        return deleteVariantResult
    }

    /** Answers pages 1..[lastPage] with 20 products each, numbered across pages. */
    fun answerPages(
        lastPage: Int,
        perPage: Int = 20,
    ) {
        for (page in 1..lastPage) {
            val products = (1..perPage).map { product(id = ((page - 1) * perPage + it).toLong()) }
            answers[page] = ApiResult.Success(ProductPage(products, currentPage = page, lastPage = lastPage, total = lastPage * perPage))
        }
    }

    companion object {
        /** The key of [variantGates] for adding a variant. */
        const val NEW_VARIANT = -1L

        /** Kepiting Bakau Super as the edit form loads it: pre order, two attributes, two variants. */
        val DETAIL =
            ProductDetail(
                id = 7,
                draft =
                    ProductDraft(
                        categoryId = 2,
                        name = "Kepiting Bakau Super",
                        description = "Kepiting bakau jantan dari tambak Muara Angke.",
                        seoDescription = "Kepiting bakau jantan grade super.",
                        condition = ProductCondition.Live,
                        salesMode = SalesMode.PreOrder,
                        dailyQuota = BigDecimal("25.000"),
                        leadTimeDays = 1,
                        cutoffTime = "15:00",
                        shelfLifeDays = 2,
                        storageInstructions = "Simpan di wadah lembap dan sejuk.",
                        isActive = true,
                        attributes = mapOf(10L to "350", 11L to "Super"),
                    ),
                isBlocked = false,
                blockReason = null,
                variants = listOf(variant(id = 1, price = 185_000), variant(id = 2, price = 215_000)),
            )

        @Suppress("LongParameterList") // Every field a test may want to vary.
        fun variant(
            id: Long = 1,
            price: Long = 185_000,
            isActive: Boolean = true,
            isStockStale: Boolean = false,
            unitLabel: String = "Kg",
            name: String = "Ukuran $id",
            unit: VariantUnit = VariantUnit.Kg,
        ) = ProductVariant(
            id = id,
            name = name,
            unitLabel = unitLabel,
            price = price,
            stock = BigDecimal("18.500"),
            isStockStale = isStockStale,
            isActive = isActive,
            unit = unit,
            minQty = BigDecimal("1.000"),
        )

        @Suppress("LongParameterList") // Every field a test may want to vary.
        fun product(
            id: Long = 1,
            name: String = "Kepiting Bakau $id",
            categoryName: String = "Kepiting Bakau",
            isActive: Boolean = true,
            isBlocked: Boolean = false,
            blockReason: String? = null,
            variants: List<ProductVariant> = listOf(variant()),
            images: List<ProductImage> =
                listOf(ProductImage(1, "http://localhost:8000/storage/p/$id.webp", "http://localhost:8000/storage/p/${id}_thumb.webp")),
        ) = Product(
            id = id,
            name = name,
            categoryName = categoryName,
            isActive = isActive,
            isBlocked = isBlocked,
            blockReason = blockReason,
            variants = variants,
            images = images,
        )
    }
}
