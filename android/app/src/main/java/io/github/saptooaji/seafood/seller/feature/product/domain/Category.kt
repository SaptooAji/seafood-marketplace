package io.github.saptooaji.seafood.seller.feature.product.domain

import io.github.saptooaji.seafood.seller.core.network.ApiResult

/**
 * A node of the category tree. Products go into leaves only; [attributes]
 * are the effective ones, inherited from parents included.
 */
data class Category(
    val id: Long,
    val name: String,
    val attributes: List<CategoryAttribute>,
    val children: List<Category>,
) {
    val isLeaf: Boolean get() = children.isEmpty()
}

/** One field a category asks of its products, such as "Berat per ekor" in grams. */
data class CategoryAttribute(
    val id: Long,
    val name: String,
    val type: AttributeType,
    /** The choices of a [AttributeType.Select]. */
    val options: List<String>,
    /** Shown after a number, such as `g`. */
    val unit: String?,
    val isRequired: Boolean,
)

enum class AttributeType {
    Text,
    Number,
    Select,
    Date,
    ;

    companion object {
        /** A type added on the server after this app version is edited as text. */
        fun from(value: String): AttributeType =
            when (value) {
                "number" -> Number
                "select" -> Select
                "date" -> Date
                else -> Text
            }
    }
}

/** The category tree (`GET categories`), kept in memory for the session. */
interface CategoryRepository {
    suspend fun tree(): ApiResult<List<Category>>
}

/** The categories from the root down to the one with [id]; empty when it is not in the tree. */
fun List<Category>.pathTo(id: Long): List<Category> =
    firstNotNullOfOrNull { category ->
        if (category.id == id) {
            listOf(category)
        } else {
            category.children
                .pathTo(id)
                .takeIf { it.isNotEmpty() }
                ?.let { listOf(category) + it }
        }
    }.orEmpty()
