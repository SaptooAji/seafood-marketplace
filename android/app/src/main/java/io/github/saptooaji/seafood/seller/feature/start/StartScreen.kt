package io.github.saptooaji.seafood.seller.feature.start

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
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
import io.github.saptooaji.seafood.seller.core.designsystem.embun.OfflineContent

/**
 * The first destination: the logo on the Embun gradient while [StartViewModel]
 * checks the token and the store, then on to where the seller belongs.
 * Without a connection it offers to try again; the seller stays signed in.
 */
@Composable
fun StartScreen(
    onDestination: (StartDestination) -> Unit,
    viewModel: StartViewModel = hiltViewModel(),
) {
    val destination by viewModel.destination.collectAsStateWithLifecycle()

    LaunchedEffect(destination) {
        if (destination != StartDestination.Checking && destination != StartDestination.Offline) onDestination(destination)
    }

    EmbunBackground(Modifier.fillMaxSize()) {
        if (destination == StartDestination.Offline) {
            OfflineContent(
                onRetry = viewModel::retry,
                modifier = Modifier.align(Alignment.Center).safeDrawingPadding().padding(16.dp),
            )
        } else {
            Image(
                painter = painterResource(R.drawable.ic_logo_mark),
                contentDescription = null,
                modifier = Modifier.align(Alignment.Center).size(72.dp),
            )
        }
    }
}
