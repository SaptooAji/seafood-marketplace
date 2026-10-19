package io.github.saptooaji.seafood.seller.feature.product

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.Product
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductImage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductPage
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductRepository
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductStatusFilter
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductVariant
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
        fun variant(
            id: Long = 1,
            price: Long = 185_000,
            isActive: Boolean = true,
            isStockStale: Boolean = false,
            unitLabel: String = "Kg",
        ) = ProductVariant(
            id = id,
            name = "Ukuran $id",
            unitLabel = unitLabel,
            price = price,
            stock = BigDecimal("18.500"),
            isStockStale = isStockStale,
            isActive = isActive,
        )

        fun product(
            id: Long = 1,
            name: String = "Kepiting Bakau $id",
            categoryName: String = "Kepiting Bakau",
            isActive: Boolean = true,
            isBlocked: Boolean = false,
            blockReason: String? = null,
            variants: List<ProductVariant> = listOf(variant()),
            images: List<ProductImage> = listOf(ProductImage(1, "http://localhost:8000/storage/p/$id.webp", "http://localhost:8000/storage/p/${id}_thumb.webp")),
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
