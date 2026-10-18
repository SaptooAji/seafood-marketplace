package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardCapitalization
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunTopBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBottomBar
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.embun.StepIndicator
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText

/** The registration destination: wires [RegisterViewModel] to the stateless screen. */
@Composable
fun RegisterScreen(
    onRegistered: () -> Unit,
    onBack: () -> Unit,
    onLogin: () -> Unit,
    viewModel: RegisterViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(uiState.registered) {
        if (uiState.registered) onRegistered()
    }

    RegisterScreen(uiState = uiState, onEvent = viewModel::onEvent, onBack = onBack, onLogin = onLogin)
}

/**
 * Step 1 of opening a store: the account. As the board VD-Daftar draws it,
 * with the main action in a glass bar fixed to the bottom.
 */
@Composable
fun RegisterScreen(
    uiState: RegisterUiState,
    onEvent: (RegisterEvent) -> Unit,
    onBack: () -> Unit,
    onLogin: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(Modifier.fillMaxSize().imePadding()) {
            Column(
                Modifier
                    .weight(1f)
                    .verticalScroll(rememberScrollState()),
            ) {
                EmbunTopBar(
                    title = stringResource(R.string.register_title),
                    subtitle = stringResource(R.string.register_subtitle),
                    onBack = onBack,
                    backLabel = stringResource(R.string.action_back),
                    backModifier = Modifier.testTag(RegisterTags.BACK),
                )
                Column(
                    modifier = Modifier.padding(start = 16.dp, end = 16.dp, top = 8.dp, bottom = 16.dp),
                    verticalArrangement = Arrangement.spacedBy(18.dp),
                ) {
                    StepIndicator(
                        steps = listOf(stringResource(R.string.register_step_account), stringResource(R.string.register_step_store)),
                        current = 1,
                    )
                    RegisterForm(uiState = uiState, onEvent = onEvent)
                }
            }

            GlassBottomBar {
                PrimaryPillButton(
                    text = stringResource(if (uiState.isSubmitting) R.string.register_submitting else R.string.register_submit),
                    onClick = { onEvent(RegisterEvent.Submit) },
                    enabled = uiState.canSubmit,
                    modifier = Modifier.testTag(RegisterTags.SUBMIT),
                )
                LoginLink(onLogin)
            }
        }
    }
}

@Composable
private fun RegisterForm(
    uiState: RegisterUiState,
    onEvent: (RegisterEvent) -> Unit,
) {
    GlassCard(Modifier.fillMaxWidth()) {
        uiState.generalError?.let { error ->
            GlassBanner(
                text = error.asString(),
                tone = if (uiState.retryAfterSeconds > 0) BannerTone.Warn else BannerTone.Danger,
            )
        }

        GlassTextField(
            value = uiState.name,
            onValueChange = { onEvent(RegisterEvent.NameChanged(it)) },
            label = stringResource(R.string.register_name_label),
            error = uiState.nameError?.asString(),
            contentType = ContentType.PersonFullName,
            keyboardOptions = KeyboardOptions(capitalization = KeyboardCapitalization.Words, imeAction = ImeAction.Next),
            modifier = Modifier.testTag(RegisterTags.NAME),
        )
        GlassTextField(
            value = uiState.email,
            onValueChange = { onEvent(RegisterEvent.EmailChanged(it)) },
            label = stringResource(R.string.register_email_label),
            error = uiState.emailError?.asString(),
            contentType = ContentType.EmailAddress,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
            modifier = Modifier.testTag(RegisterTags.EMAIL),
        )
        GlassTextField(
            value = uiState.phone,
            onValueChange = { onEvent(RegisterEvent.PhoneChanged(it)) },
            label = stringResource(R.string.register_phone_label),
            error = uiState.phoneError?.asString(),
            helpText = stringResource(R.string.register_phone_help),
            contentType = ContentType.PhoneNumber,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone, imeAction = ImeAction.Next),
            modifier = Modifier.testTag(RegisterTags.PHONE),
        )
        GlassTextField(
            value = uiState.password,
            onValueChange = { onEvent(RegisterEvent.PasswordChanged(it)) },
            label = stringResource(R.string.register_password_label),
            error = uiState.passwordError?.asString(),
            helpText = stringResource(R.string.register_password_help),
            contentType = ContentType.NewPassword,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Next, autoCorrectEnabled = false),
            visualTransformation = PasswordVisualTransformation(),
            modifier = Modifier.testTag(RegisterTags.PASSWORD),
        )
        GlassTextField(
            value = uiState.passwordConfirmation,
            onValueChange = { onEvent(RegisterEvent.PasswordConfirmationChanged(it)) },
            label = stringResource(R.string.register_password_confirmation_label),
            error = uiState.passwordConfirmationError?.asString(),
            contentType = ContentType.NewPassword,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done, autoCorrectEnabled = false),
            keyboardActions = KeyboardActions(onDone = { onEvent(RegisterEvent.Submit) }),
            visualTransformation = PasswordVisualTransformation(),
            modifier = Modifier.testTag(RegisterTags.PASSWORD_CONFIRMATION),
        )
    }
}

@Composable
private fun LoginLink(onLogin: () -> Unit) {
    val text =
        buildAnnotatedString {
            append(stringResource(R.string.register_login_prompt))
            append(" ")
            withStyle(SpanStyle(color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.ExtraBold)) {
                append(stringResource(R.string.register_login_link))
            }
        }
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = EmbunColors.Muted,
        textAlign = TextAlign.Center,
        modifier =
            Modifier
                .testTag(RegisterTags.LOGIN)
                .fillMaxWidth()
                .clip(CircleShape)
                .clickable(role = Role.Button, onClick = onLogin)
                .padding(vertical = 8.dp),
    )
}

@Preview(widthDp = 390, heightDp = 900)
@Composable
private fun RegisterScreenPreview() {
    SeafoodTheme {
        RegisterScreen(
            uiState =
                RegisterUiState(
                    name = "Budi Santoso",
                    email = "budi@example.com",
                    emailError = UiText.Resource(R.string.register_error_email_taken),
                    phone = "0812 3456 7890",
                ),
            onEvent = {},
            onBack = {},
            onLogin = {},
        )
    }
}
