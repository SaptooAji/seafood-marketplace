package io.github.saptooaji.seafood.seller.feature.settings.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.CircularProgressIndicator
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.heading
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import coil3.compose.AsyncImage
import io.github.saptooaji.seafood.seller.BuildConfig
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.feature.auth.domain.User
import io.github.saptooaji.seafood.seller.feature.product.ui.DangerPillButton
import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import io.github.saptooaji.seafood.seller.feature.store.domain.Store
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreRole
import io.github.saptooaji.seafood.seller.feature.store.domain.StoreStatus
import kotlinx.coroutines.delay
import java.time.LocalTime

/**
 * The Pengaturan tab: wires [SettingsViewModel] to the stateless screen.
 *
 * @param storeEdited how the store form ended, handed back by the navigation;
 *   [onStoreEditedTaken] clears it once the tab has reacted
 * @param onModalChange true while a sheet is open, so the tab capsule hides
 *   and does not cover the sheet's buttons
 */
@Composable
fun SettingsScreen(
    onEditStore: () -> Unit,
    onShippingMethods: () -> Unit,
    onSignedOut: () -> Unit,
    storeEdited: EditStoreResult?,
    onStoreEditedTaken: () -> Unit,
    onModalChange: (Boolean) -> Unit,
    viewModel: SettingsViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(storeEdited) {
        if (storeEdited != null) {
            viewModel.onEvent(SettingsEvent.StoreEdited(storeEdited))
            onStoreEditedTaken()
        }
    }
    LaunchedEffect(uiState.signedOut) {
        if (uiState.signedOut) onSignedOut()
    }

    SettingsScreen(
        uiState = uiState,
        onEvent = viewModel::onEvent,
        onEditStore = onEditStore,
        onShippingMethods = onShippingMethods,
        versionName = BuildConfig.VERSION_NAME,
        onModalChange = onModalChange,
    )
}

/** Pengaturan as the boards VD-Pengaturan and its states draw it. */
@Composable
fun SettingsScreen(
    uiState: SettingsUiState,
    onEvent: (SettingsEvent) -> Unit,
    onEditStore: () -> Unit,
    onShippingMethods: () -> Unit,
    versionName: String,
    modifier: Modifier = Modifier,
    onModalChange: (Boolean) -> Unit = {},
) {
    var reminderOpen by rememberSaveable { mutableStateOf(false) }
    val modal = uiState.profile != null || uiState.confirmingSignOut || reminderOpen
    val modalChange by rememberUpdatedState(onModalChange)
    LaunchedEffect(modal) { modalChange(modal) }
    DisposableEffect(Unit) { onDispose { modalChange(false) } }

    Box(modifier.fillMaxSize()) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                // Room for the capsule floating over the bottom of the content.
                .padding(start = 16.dp, end = 16.dp, top = 26.dp, bottom = 110.dp),
            verticalArrangement = Arrangement.spacedBy(14.dp),
        ) {
            Text(
                text = stringResource(R.string.settings_title),
                style =
                    MaterialTheme.typography.headlineLarge.copy(
                        fontSize = 34.sp,
                        fontWeight = FontWeight.Bold,
                        letterSpacing = (-0.04).em,
                    ),
                modifier = Modifier.padding(horizontal = 4.dp).semantics { heading() },
            )
            Notice(uiState.notice, onEvent)
            Body(uiState, onEvent, onEditStore, onShippingMethods, versionName) { reminderOpen = true }
        }
        Sheets(uiState, onEvent, reminderOpen) { reminderOpen = false }
    }
}

@Composable
private fun Body(
    uiState: SettingsUiState,
    onEvent: (SettingsEvent) -> Unit,
    onEditStore: () -> Unit,
    onShippingMethods: () -> Unit,
    versionName: String,
    onOpenReminder: () -> Unit,
) {
    val user = uiState.user
    val store = uiState.store
    when {
        uiState.trouble != null -> {
            OfflineContent(
                onRetry = { onEvent(SettingsEvent.Retry) },
                retryModifier = Modifier.testTag(SettingsTags.RETRY),
                trouble = uiState.trouble,
            )
            SignOutButton(onEvent)
        }
        user == null || store == null ->
            Box(Modifier.fillMaxWidth().padding(top = 48.dp), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        else -> {
            ProfileCard(user, onEvent)
            StoreCard(uiState, store, onEditStore, onShippingMethods, onOpenReminder)
            SignOutButton(onEvent)
            Text(
                text = stringResource(R.string.settings_version, versionName),
                style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp),
                color = EmbunColors.Muted,
                textAlign = TextAlign.Center,
                modifier = Modifier.fillMaxWidth(),
            )
        }
    }
}

@Composable
private fun BoxScope.Sheets(
    uiState: SettingsUiState,
    onEvent: (SettingsEvent) -> Unit,
    reminderOpen: Boolean,
    onCloseReminder: () -> Unit,
) {
    val profile = uiState.profile
    val store = uiState.store
    when {
        profile != null -> ProfileSheet(profile, uiState.user?.email.orEmpty(), onEvent)
        reminderOpen && store != null ->
            ReminderSheet(
                initial = store.stockReminderTime,
                onSave = { time ->
                    onEvent(SettingsEvent.SaveReminder(time))
                    onCloseReminder()
                },
                onDismiss = onCloseReminder,
            )
        uiState.confirmingSignOut -> SignOutSheet(onEvent)
    }
}

/** A refused or failed change; it goes away by itself after a while. */
@Composable
private fun Notice(
    notice: SettingsNotice?,
    onEvent: (SettingsEvent) -> Unit,
) {
    if (notice == null) return
    LaunchedEffect(notice) {
        delay(NOTICE_MILLIS)
        onEvent(SettingsEvent.NoticeShown)
    }
    val text =
        when (notice) {
            SettingsNotice.OwnerOnly -> R.string.settings_owner_only
            SettingsNotice.NoInternet -> R.string.error_network
            SettingsNotice.Server -> R.string.error_server
        }
    GlassBanner(text = stringResource(text), tone = BannerTone.Danger)
}

@Composable
private fun ProfileCard(
    user: User,
    onEvent: (SettingsEvent) -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        Row(horizontalArrangement = Arrangement.spacedBy(14.dp), verticalAlignment = Alignment.CenterVertically) {
            Avatar(user)
            Column(Modifier.weight(1f)) {
                Text(text = user.name, style = MaterialTheme.typography.titleMedium.copy(fontWeight = FontWeight.Bold, fontSize = 17.sp))
                Text(text = user.email, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp), color = EmbunColors.Muted)
                user.phone?.let {
                    Text(text = it, style = MaterialTheme.typography.bodyMedium.copy(fontSize = 13.sp), color = EmbunColors.Muted)
                }
            }
            Surface(
                onClick = { onEvent(SettingsEvent.EditProfile) },
                shape = PillShape,
                color = EmbunColors.GlassStrong,
                modifier = Modifier.heightIn(min = 44.dp).testTag(SettingsTags.EDIT_PROFILE),
            ) {
                Box(contentAlignment = Alignment.Center, modifier = Modifier.padding(horizontal = 20.dp, vertical = 12.dp)) {
                    Text(
                        text = stringResource(R.string.settings_edit_profile),
                        style = MaterialTheme.typography.labelLarge.copy(fontSize = 15.sp),
                    )
                }
            }
        }
    }
}

/** The avatar from the account, or the first letter of the name without one. */
@Composable
private fun Avatar(user: User) {
    Box(
        Modifier.size(56.dp).clip(CircleShape).background(AvatarGround),
        contentAlignment = Alignment.Center,
    ) {
        if (user.avatar != null) {
            AsyncImage(model = user.avatar, contentDescription = null, contentScale = ContentScale.Crop, modifier = Modifier.fillMaxSize())
        } else {
            Text(
                text = SettingsLabels.initial(user.name),
                style = MaterialTheme.typography.titleLarge.copy(fontSize = 22.sp, fontWeight = FontWeight.ExtraBold),
            )
        }
    }
}

@Composable
private fun SignOutButton(onEvent: (SettingsEvent) -> Unit) {
    DangerPillButton(
        text = stringResource(R.string.settings_sign_out),
        onClick = { onEvent(SettingsEvent.AskSignOut) },
        modifier = Modifier.testTag(SettingsTags.SIGN_OUT),
    )
}

private const val NOTICE_MILLIS = 6_000L
private val AvatarGround = Color(0xFFCFE3D7)

@Preview(widthDp = 390, heightDp = 1250)
@Composable
private fun SettingsScreenPreview() {
    SeafoodTheme {
        EmbunBackground {
            SettingsScreen(
                uiState =
                    SettingsUiState(
                        isLoading = false,
                        user = User(1, "Budi Santoso", "budi@example.com", "0812 3456 7890", null),
                        store = PREVIEW_STORE,
                    ),
                onEvent = {},
                onEditStore = {},
                onShippingMethods = {},
                versionName = "0.4.0",
            )
        }
    }
}

private val PREVIEW_STORE =
    Store(
        id = 1,
        name = "UD Kepiting Pak Budi",
        slug = "ud-kepiting-pak-budi",
        description = null,
        phone = "081234567890",
        address = "Jl. Dermaga Ujung No. 12",
        city = "Jakarta Utara",
        lat = null,
        lng = null,
        status = StoreStatus.Active,
        statusLabel = "Aktif",
        rejectionReason = null,
        bank = BankAccount("BCA", "1234567890", "Budi Santoso"),
        role = StoreRole.Owner,
        stockReminderTime = LocalTime.of(6, 0),
    )
