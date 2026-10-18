package io.github.saptooaji.seafood.seller.feature.auth.ui

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.offset
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.ContentType
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.SpanStyle
import androidx.compose.ui.text.buildAnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.text.withStyle
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.em
import androidx.compose.ui.unit.sp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassTextField
import io.github.saptooaji.seafood.seller.core.designsystem.embun.PrimaryPillButton
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme
import io.github.saptooaji.seafood.seller.core.util.UiText
import java.time.LocalTime

/** The sign-in destination: wires [LoginViewModel] to the stateless screen. */
@Composable
fun LoginScreen(
    onLoggedIn: () -> Unit,
    onRegister: () -> Unit,
    viewModel: LoginViewModel = hiltViewModel(),
) {
    val uiState by viewModel.uiState.collectAsStateWithLifecycle()

    LaunchedEffect(uiState.loggedIn) {
        if (uiState.loggedIn) onLoggedIn()
    }

    LoginScreen(uiState = uiState, onEvent = viewModel::onEvent, onRegister = onRegister)
}

/** Sign-in with email and password, as the board VD-Masuk draws it. */
@Composable
fun LoginScreen(
    uiState: LoginUiState,
    onEvent: (LoginEvent) -> Unit,
    onRegister: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(
            modifier =
                Modifier
                    .fillMaxSize()
                    .verticalScroll(rememberScrollState())
                    .imePadding()
                    .navigationBarsPadding(),
        ) {
            LoginHero()
            Column(
                modifier = Modifier.padding(start = 16.dp, end = 16.dp, bottom = if (uiState.sessionExpired) 96.dp else 20.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
            ) {
                Text(
                    text = stringResource(greetingFor(remember { LocalTime.now().hour })),
                    style =
                        MaterialTheme.typography.displaySmall.copy(
                            fontWeight = FontWeight.Bold,
                            fontSize = 36.sp,
                            lineHeight = 37.sp,
                            letterSpacing = (-0.045).em,
                        ),
                    color = MaterialTheme.colorScheme.onBackground,
                    modifier = Modifier.padding(horizontal = 4.dp),
                )
                LoginForm(uiState = uiState, onEvent = onEvent, onRegister = onRegister)
            }
        }

        if (uiState.sessionExpired) {
            SessionEndedBar(Modifier.align(Alignment.BottomCenter))
        }
    }
}

@Composable
private fun LoginForm(
    uiState: LoginUiState,
    onEvent: (LoginEvent) -> Unit,
    onRegister: () -> Unit,
) {
    var passwordVisible by rememberSaveable { mutableStateOf(false) }

    GlassCard(Modifier.fillMaxWidth()) {
        uiState.generalError?.let { error ->
            GlassBanner(
                text = error.asString(),
                tone = if (uiState.retryAfterSeconds > 0) BannerTone.Warn else BannerTone.Danger,
            )
        }

        GlassTextField(
            value = uiState.email,
            onValueChange = { onEvent(LoginEvent.EmailChanged(it)) },
            label = stringResource(R.string.login_email_label),
            error = uiState.emailError?.asString(),
            contentType = ContentType.EmailAddress,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next),
            modifier = Modifier.testTag(LoginTags.EMAIL),
        )

        GlassTextField(
            value = uiState.password,
            onValueChange = { onEvent(LoginEvent.PasswordChanged(it)) },
            label = stringResource(R.string.login_password_label),
            error = uiState.passwordError?.asString(),
            contentType = ContentType.Password,
            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done, autoCorrectEnabled = false),
            keyboardActions = KeyboardActions(onDone = { onEvent(LoginEvent.Submit) }),
            visualTransformation = if (passwordVisible) VisualTransformation.None else PasswordVisualTransformation(),
            trailing = {
                Text(
                    text = stringResource(if (passwordVisible) R.string.login_password_hide else R.string.login_password_show),
                    style = MaterialTheme.typography.labelLarge,
                    fontWeight = FontWeight.ExtraBold,
                    color = MaterialTheme.colorScheme.onSurface,
                    modifier =
                        Modifier
                            .clip(CircleShape)
                            .clickable(role = Role.Button) { passwordVisible = !passwordVisible }
                            .padding(horizontal = 6.dp, vertical = 12.dp),
                )
            },
            modifier = Modifier.testTag(LoginTags.PASSWORD),
        )

        PrimaryPillButton(
            text = stringResource(if (uiState.isSubmitting) R.string.login_submitting else R.string.login_submit),
            onClick = { onEvent(LoginEvent.Submit) },
            enabled = uiState.canSubmit,
            modifier = Modifier.testTag(LoginTags.SUBMIT),
        )

        RegisterLink(onRegister)
    }
}

@Composable
private fun RegisterLink(onRegister: () -> Unit) {
    val text =
        buildAnnotatedString {
            append(stringResource(R.string.login_register_prompt))
            append(" ")
            withStyle(SpanStyle(color = MaterialTheme.colorScheme.onSurface, fontWeight = FontWeight.ExtraBold)) {
                append(stringResource(R.string.login_register_link))
            }
        }
    Text(
        text = text,
        style = MaterialTheme.typography.bodyMedium,
        color = EmbunColors.Muted,
        textAlign = TextAlign.Center,
        modifier =
            Modifier
                .testTag(LoginTags.REGISTER)
                .fillMaxWidth()
                .clip(CircleShape)
                .clickable(role = Role.Button, onClick = onRegister)
                .padding(vertical = 10.dp),
    )
}

/** Three round photos of the day's catch, the logo, and the seller label. Decorative. */
@Composable
private fun LoginHero() {
    Box(
        Modifier
            .fillMaxWidth()
            .statusBarsPadding()
            .height(292.dp),
    ) {
        RoundPhoto(R.drawable.login_photo_crab, size = 200.dp, x = 150.dp, y = 28.dp)
        RoundPhoto(R.drawable.login_photo_squid, size = 120.dp, x = 36.dp, y = 120.dp)
        RoundPhoto(R.drawable.login_photo_snapper, size = 76.dp, x = 290.dp, y = 210.dp)
        Row(
            modifier = Modifier.offset(x = 24.dp, y = 28.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.spacedBy(8.dp),
        ) {
            Image(painter = painterResource(R.drawable.ic_logo_mark), contentDescription = null, modifier = Modifier.size(34.dp))
            Text(
                text = stringResource(R.string.login_brand),
                style = MaterialTheme.typography.titleMedium.copy(fontSize = 15.sp),
                color = MaterialTheme.colorScheme.onBackground,
            )
        }
    }
}

@Composable
private fun RoundPhoto(
    @DrawableRes photo: Int,
    size: Dp,
    x: Dp,
    y: Dp,
) {
    Image(
        painter = painterResource(photo),
        contentDescription = null,
        contentScale = ContentScale.Crop,
        modifier =
            Modifier
                .offset(x = x, y = y)
                .size(size)
                .shadow(size / PHOTO_SHADOW_RATIO, CircleShape, ambientColor = EmbunColors.PhotoShadow, spotColor = EmbunColors.PhotoShadow)
                .clip(CircleShape),
    )
}

/** The dark bar at the bottom after a 401 sent the seller here (MOB-6). */
@Composable
private fun SessionEndedBar(modifier: Modifier = Modifier) {
    Text(
        text = stringResource(R.string.login_session_expired),
        style = MaterialTheme.typography.bodyMedium,
        color = Color.White,
        modifier =
            modifier
                .navigationBarsPadding()
                .padding(16.dp)
                .fillMaxWidth()
                .semantics { liveRegion = LiveRegionMode.Polite }
                .shadow(12.dp, SessionBarShape, ambientColor = EmbunColors.PhotoShadow, spotColor = EmbunColors.PhotoShadow)
                .clip(SessionBarShape)
                .background(MaterialTheme.colorScheme.onBackground)
                .padding(horizontal = 16.dp, vertical = 14.dp),
    )
}

/** A photo's shadow grows with the photo: an eighth of its size. */
private const val PHOTO_SHADOW_RATIO = 8

private val SessionBarShape = RoundedCornerShape(20.dp)

private const val MORNING_ENDS = 11
private const val AFTERNOON_ENDS = 15
private const val EVENING_ENDS = 18
private const val NIGHT_ENDS = 4

/** The headline greets by the time of day, as people do in Indonesian. */
private fun greetingFor(hour: Int): Int =
    when {
        hour < NIGHT_ENDS -> R.string.login_greeting_night
        hour < MORNING_ENDS -> R.string.login_greeting_morning
        hour < AFTERNOON_ENDS -> R.string.login_greeting_afternoon
        hour < EVENING_ENDS -> R.string.login_greeting_evening
        else -> R.string.login_greeting_night
    }

@Preview(widthDp = 390, heightDp = 900)
@Composable
private fun LoginScreenPreview() {
    SeafoodTheme {
        LoginScreen(
            uiState = LoginUiState(email = "budi@example.com", emailError = UiText.Resource(R.string.login_error_credentials)),
            onEvent = {},
            onRegister = {},
        )
    }
}
