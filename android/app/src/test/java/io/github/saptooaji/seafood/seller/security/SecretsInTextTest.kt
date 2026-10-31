package io.github.saptooaji.seafood.seller.security

import io.github.saptooaji.seafood.seller.feature.auth.data.LoginRequest
import io.github.saptooaji.seafood.seller.feature.auth.data.RegisterRequest
import io.github.saptooaji.seafood.seller.feature.auth.data.TokenResponse
import io.github.saptooaji.seafood.seller.feature.auth.data.UserDto
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginEvent
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginUiState
import io.github.saptooaji.seafood.seller.feature.auth.ui.RegisterEvent
import io.github.saptooaji.seafood.seller.feature.auth.ui.RegisterUiState
import io.github.saptooaji.seafood.seller.feature.settings.ui.EditStoreUiState
import io.github.saptooaji.seafood.seller.feature.store.ui.OpenStoreUiState
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreFormState
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test

/**
 * Passwords, the API token, and the bank account never show in `toString()`
 * (docs/security-standards.md §11, "Data sensitif di perangkat"): a crash
 * report or a stray log line prints objects that way. What is not secret
 * stays readable, so the text still helps when debugging.
 */
class SecretsInTextTest {
    @Test
    fun `sign-in and registration requests never print the password`() {
        val login = LoginRequest(email = "budi@example.com", password = PASSWORD, deviceName = "Google Pixel 9").toString()
        val register =
            RegisterRequest(
                name = "Budi Santoso",
                email = "budi@example.com",
                phone = "081234567890",
                password = PASSWORD,
                passwordConfirmation = PASSWORD,
                deviceName = "Google Pixel 9",
            ).toString()

        assertFalse(login.contains(PASSWORD), login)
        assertFalse(register.contains(PASSWORD), register)
        assertTrue(login.contains("budi@example.com"), login)
    }

    @Test
    fun `a sign-in answer never prints the API token`() {
        val answer = TokenResponse(token = TOKEN, user = UserDto(id = 7, name = "Budi Santoso", email = "budi@example.com")).toString()

        assertFalse(answer.contains(TOKEN), answer)
        assertTrue(answer.contains("Budi Santoso"), answer)
    }

    @Test
    fun `the sign-in screen never prints the password`() {
        val state = LoginUiState(email = "budi@example.com", password = PASSWORD).toString()

        assertFalse(state.contains(PASSWORD), state)
        assertTrue(state.contains("budi@example.com"), state)
        assertFalse(LoginEvent.PasswordChanged(PASSWORD).toString().contains(PASSWORD))
    }

    @Test
    fun `the registration screen never prints either password`() {
        val state = RegisterUiState(name = "Budi Santoso", password = PASSWORD, passwordConfirmation = "$PASSWORD!").toString()

        assertFalse(state.contains(PASSWORD), state)
        assertTrue(state.contains("Budi Santoso"), state)
        assertFalse(RegisterEvent.PasswordChanged(PASSWORD).toString().contains(PASSWORD))
        assertFalse(RegisterEvent.PasswordConfirmationChanged(PASSWORD).toString().contains(PASSWORD))
    }

    @Test
    fun `the store form never prints the bank account, opening or editing`() {
        val form =
            StoreFormState(name = "UD Kepiting Pak Budi", bankName = "BCA", bankAccountNo = ACCOUNT_NO, bankAccountName = ACCOUNT_NAME)

        for (text in listOf(form.toString(), OpenStoreUiState(form = form).toString(), EditStoreUiState(form = form).toString())) {
            assertFalse(text.contains(ACCOUNT_NO), text)
            assertFalse(text.contains(ACCOUNT_NAME), text)
            assertTrue(text.contains("UD Kepiting Pak Budi"), text)
        }
    }

    private companion object {
        const val PASSWORD = "rahasia-budi-123"
        const val TOKEN = "sfm_secret_api_token"
        const val ACCOUNT_NO = "7700112233"
        const val ACCOUNT_NAME = "Budi Santoso Rekening"
    }
}
