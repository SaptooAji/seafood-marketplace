package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.feature.welcome.ui.WelcomeScreen
import kotlinx.coroutines.flow.Flow

/**
 * Every screen of the app. Routes are type-safe `@Serializable` objects or
 * classes, one per destination.
 *
 * When the session ends (a 401 from the API, MOB-6), the back stack is
 * cleared and the app returns to its first screen, which becomes sign-in in
 * #231.
 */
@Composable
fun SellerNavHost(sessionEvents: Flow<SessionEvent>) {
    val navController = rememberNavController()

    LaunchedEffect(navController, sessionEvents) {
        sessionEvents.collect { event ->
            when (event) {
                SessionEvent.SignedOut ->
                    navController.navigate(WelcomeRoute) {
                        popUpTo(navController.graph.id) { inclusive = true }
                    }
            }
        }
    }

    NavHost(navController = navController, startDestination = WelcomeRoute) {
        composable<WelcomeRoute> { WelcomeScreen() }
    }
}
