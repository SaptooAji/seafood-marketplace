package io.github.saptooaji.seafood.seller.navigation

import kotlinx.serialization.Serializable

/** Checks the stored token, then moves on to sign-in or the store. */
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

/** Where a signed-in seller lands, until the store status route in #235. */
@Serializable
data object WelcomeRoute
