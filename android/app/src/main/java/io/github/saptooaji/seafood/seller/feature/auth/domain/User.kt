package io.github.saptooaji.seafood.seller.feature.auth.domain

/** The signed-in account, as `UserResource` on the server describes it. */
data class User(
    val id: Long,
    val name: String,
    val email: String,
    val phone: String?,
    val avatar: String?,
)
