package io.github.saptooaji.seafood.seller.navigation

import kotlinx.serialization.Serializable

/** Checks the stored token and the store, then moves on to where the seller belongs. */
@Serializable
data object StartRoute

/**
 * Sign-in. [sessionExpired] is true when a 401 ended the session (MOB-6); the
 * screen then says why the seller is here.
 */
@Serializable
data class LoginRoute(
    val sessionExpired: Boolean = false,
)

/** Creating a seller account, opened from sign-in. */
@Serializable
data object RegisterRoute

/** Opening a store: right after registration, or for a seller without one. */
@Serializable
data object OpenStoreRoute

/**
 * The store waiting for, or refused by, the admin. [serverMessage] is the
 * server's reason when another screen was refused, such as a suspension. The
 * store itself is read again by the screen, never passed here, so the bank
 * account never lands in the saved navigation state.
 */
@Serializable
data class StoreStatusRoute(
    val serverMessage: String? = null,
)

/** The main screens. [storePending] shows that the store still waits for the admin. */
@Serializable
data class MainRoute(
    val storePending: Boolean = false,
)

/** Adding a product, opened from the products tab. */
@Serializable
data object ProductAddRoute

/** Editing the product with [productId]; the form reads it from its SavedStateHandle. */
@Serializable
data class ProductEditRoute(
    val productId: Long,
)

/**
 * Cutting a picked photo before it is uploaded (#241). [source] is its uri,
 * from the Photo Picker or the camera; the screen reads it from its
 * SavedStateHandle.
 */
@Serializable
data class PhotoCropRoute(
    val source: String,
)
