package io.github.saptooaji.seafood.seller.feature.settings.ui

import io.github.saptooaji.seafood.seller.core.network.ConnectionTrouble
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import java.time.LocalTime

data class SettingsUiState(
    val isLoading: Boolean = true,
    /** Why the tab could not load; "Keluar" still works then. */
    val trouble: ConnectionTrouble? = null,
    val user: User? = null,
    /** The store as `StoreResource` sends it; staff get no bank account. */
    val store: Store? = null,
    /** The open "Ubah profil" sheet, or null when it is closed. */
    val profile: ProfileFormState? = null,
    val isSavingReminder: Boolean = false,
    /** A message over the tab, such as a refused change; cleared once shown. */
    val notice: SettingsNotice? = null,
    val confirmingSignOut: Boolean = false,
    val isSigningOut: Boolean = false,
    /** The token is gone; the screen moves on to sign-in. */
    val signedOut: Boolean = false,
) {
    /** The server's role decides, never whether a bank account came. */
    val isOwner: Boolean get() = store?.role == StoreRole.Owner
}

enum class SettingsNotice {
    /** A 403 on a change: only the owner may change the store. */
    OwnerOnly,
    NoInternet,
    Server,
}

/** How the store form ended, told to Pengaturan when it closes. */
enum class EditStoreResult {
    Saved,

    /** The server answered 403: the seller is no longer the owner. */
    NotOwner,
}

enum class ProfileField { Name, Phone }

/** The "Ubah profil" sheet as typed. */
data class ProfileFormState(
    val name: String = "",
    val phone: String = "",
    val errors: Map<ProfileField, UiText> = emptyMap(),
    /** An error that belongs to no field: no connection or a server error. */
    val generalError: UiText? = null,
    val isSaving: Boolean = false,
)

sealed interface SettingsEvent {
    data object Retry : SettingsEvent

    data class StoreEdited(
        val result: EditStoreResult,
    ) : SettingsEvent

    data object EditProfile : SettingsEvent

    data class ProfileChanged(
        val field: ProfileField,
        val value: String,
    ) : SettingsEvent

    data object SaveProfile : SettingsEvent

    data object CloseProfile : SettingsEvent

    /** Sets the morning reminder, or removes it with null. */
    data class SaveReminder(
        val time: LocalTime?,
    ) : SettingsEvent

    data object NoticeShown : SettingsEvent

    data object AskSignOut : SettingsEvent

    data object CancelSignOut : SettingsEvent

    data object SignOut : SettingsEvent
}

/** Test tags of the Pengaturan tab and its sheets. */
object SettingsTags {
    const val EDIT_PROFILE = "settings_edit_profile"
    const val EDIT_STORE = "settings_edit_store"
    const val BANK = "settings_bank"
    const val SHIPPING = "settings_shipping"
    const val REMINDER = "settings_reminder"
    const val SIGN_OUT = "settings_sign_out"
    const val SIGN_OUT_CONFIRM = "settings_sign_out_confirm"
    const val SIGN_OUT_CANCEL = "settings_sign_out_cancel"
    const val RETRY = "settings_retry"
    const val PROFILE_NAME = "settings_profile_name"
    const val PROFILE_PHONE = "settings_profile_phone"
    const val PROFILE_EMAIL = "settings_profile_email"
    const val PROFILE_SAVE = "settings_profile_save"
    const val PROFILE_CLOSE = "settings_profile_close"
    const val REMINDER_HOUR = "settings_reminder_hour"
    const val REMINDER_MINUTE = "settings_reminder_minute"
    const val REMINDER_SAVE = "settings_reminder_save"
    const val REMINDER_REMOVE = "settings_reminder_remove"
    const val REMINDER_CLOSE = "settings_reminder_close"
}
