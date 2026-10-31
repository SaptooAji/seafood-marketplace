package io.github.saptooaji.seafood.seller.feature.auth.data

import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

@Serializable
data class LoginRequest(
    val email: String,
    val password: String,
    @SerialName("device_name") val deviceName: String,
)

@Serializable
data class GoogleLoginRequest(
    @SerialName("id_token") val idToken: String,
    @SerialName("device_name") val deviceName: String,
) {
    // The ID token is a credential: keep it out of logs (MOB-4).
    override fun toString() = "GoogleLoginRequest(idToken=██, deviceName=$deviceName)"
}

@Serializable
data class RegisterRequest(
    val name: String,
    val email: String,
    val phone: String,
    val password: String,
    @SerialName("password_confirmation") val passwordConfirmation: String,
    @SerialName("device_name") val deviceName: String,
)

/** The body of `PUT me`. */
@Serializable
data class UpdateProfileRequest(
    val name: String,
    val phone: String,
)

/** The answer to a sign-in or a registration: `{"token": "...", "user": {...}}`. */
@Serializable
data class TokenResponse(
    val token: String,
    val user: UserDto,
)

/** A Laravel API resource answer: `{"data": {...}}`. */
@Serializable
data class DataEnvelope<T>(
    val data: T,
)

@Serializable
data class UserDto(
    val id: Long,
    val name: String,
    val email: String,
    val phone: String? = null,
    val avatar: String? = null,
) {
    fun toDomain() = User(id = id, name = name, email = email, phone = phone, avatar = avatar)
}
