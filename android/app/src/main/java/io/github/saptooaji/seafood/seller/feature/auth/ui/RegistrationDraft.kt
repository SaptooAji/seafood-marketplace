package io.github.saptooaji.seafood.seller.feature.auth.ui

import javax.inject.Inject
import javax.inject.Singleton

/**
 * The registration fields that outlive the screen, so a seller who goes back
 * to sign-in and returns finds the name, email, and phone still filled in.
 * Kept in memory only, and never the passwords.
 */
@Singleton
class RegistrationDraft
    @Inject
    constructor() {
        var name: String = ""
        var email: String = ""
        var phone: String = ""

        fun clear() {
            name = ""
            email = ""
            phone = ""
        }
    }
