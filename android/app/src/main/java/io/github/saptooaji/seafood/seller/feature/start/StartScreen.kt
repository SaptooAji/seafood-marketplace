package io.github.saptooaji.seafood.seller.feature.start

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.size
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import androidx.hilt.lifecycle.viewmodel.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground

/**
 * The first destination: the logo on the Embun gradient while [StartViewModel]
 * checks the stored token, then on to sign-in or the store.
 */
@Composable
fun StartScreen(
    onSignIn: () -> Unit,
    onMain: () -> Unit,
    viewModel: StartViewModel = hiltViewModel(),
) {
    val destination by viewModel.destination.collectAsStateWithLifecycle()

    LaunchedEffect(destination) {
        when (destination) {
            StartDestination.Checking -> Unit
            StartDestination.SignIn -> onSignIn()
            StartDestination.Main -> onMain()
        }
    }

    EmbunBackground(Modifier.fillMaxSize()) {
        Image(
            painter = painterResource(R.drawable.ic_logo_mark),
            contentDescription = null,
            modifier = Modifier.align(Alignment.Center).size(72.dp),
        )
    }
}
