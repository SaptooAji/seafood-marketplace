package io.github.saptooaji.seafood.seller.feature.product

import io.github.saptooaji.seafood.seller.core.network.ApiResult
import io.github.saptooaji.seafood.seller.feature.product.domain.AttributeType
import io.github.saptooaji.seafood.seller.feature.product.domain.Category
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryAttribute
import io.github.saptooaji.seafood.seller.feature.product.domain.CategoryRepository

/** A [CategoryRepository] answering with a small seafood tree, recording every call. */
class FakeCategoryRepository : CategoryRepository {
    var calls = 0
    var result: ApiResult<List<Category>> = ApiResult.Success(TREE)

    override suspend fun tree(): ApiResult<List<Category>> {
        calls++
        return result
    }

    companion object {
        val WEIGHT = CategoryAttribute(10, "Berat per ekor", AttributeType.Number, emptyList(), unit = "g", isRequired = true)
        val GRADE = CategoryAttribute(11, "Grade", AttributeType.Select, listOf("Super", "Jantan", "Betina"), unit = null, isRequired = false)
        val SIZE = CategoryAttribute(12, "Ukuran", AttributeType.Text, emptyList(), unit = null, isRequired = true)
        val HARVESTED = CategoryAttribute(13, "Tanggal panen", AttributeType.Date, emptyList(), unit = null, isRequired = false)

        val BAKAU = Category(2, "Bakau", listOf(WEIGHT, GRADE), emptyList())
        val SOKA = Category(3, "Soka", listOf(WEIGHT), emptyList())
        val KEPITING = Category(1, "Kepiting", listOf(WEIGHT), listOf(BAKAU, SOKA))
        val VANAME = Category(5, "Vaname", listOf(SIZE), emptyList())
        val UDANG = Category(4, "Udang", emptyList(), listOf(VANAME))
        val TIRAM = Category(6, "Tiram", emptyList(), emptyList())
        val KERANG = Category(7, "Kerang", listOf(HARVESTED), emptyList())

        val TREE = listOf(KEPITING, UDANG, TIRAM, KERANG)
    }
}
