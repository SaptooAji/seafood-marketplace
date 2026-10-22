package io.github.saptooaji.seafood.seller.feature.product.domain

/**
 * What the AI suggests for the product being written (#240). The server has
 * already stripped any markup; the app still shows it as plain text only.
 */
data class DescriptionSuggestion(
    val description: String,
    val seoDescription: String,
)
