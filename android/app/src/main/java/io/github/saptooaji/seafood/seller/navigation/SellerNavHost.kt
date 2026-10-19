package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.lifecycle.compose.dropUnlessResumed
import androidx.navigation.NavController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginScreen
import io.github.saptooaji.seafood.seller.feature.auth.ui.RegisterScreen
import io.github.saptooaji.seafood.seller.feature.start.StartScreen
import io.github.saptooaji.seafood.seller.feature.store.ui.OpenStoreScreen
import io.github.saptooaji.seafood.seller.feature.welcome.ui.WelcomeScreen
import kotlinx.coroutines.flow.Flow

/**
 * Every screen of the app. Routes are type-safe `@Serializable` objects or
 * classes, one per destination (Routes.kt).
 *
 * When the session ends (a 401 from the API, MOB-6), the back stack is
 * cleared and the seller is sent to sign in, with a message saying why.
 */
@Composable
fun SellerNavHost(sessionEvents: Flow<SessionEvent>) {
    val navController = rememberNavController()

    LaunchedEffect(navController, sessionEvents) {
        sessionEvents.collect { event ->
            when (event) {
                SessionEvent.SignedOut -> navController.startOver(LoginRoute(sessionExpired = true))
            }
        }
    }

    NavHost(navController = navController, startDestination = StartRoute) {
        composable<StartRoute> {
            StartScreen(
                onSignIn = { navController.startOver(LoginRoute()) },
                onMain = { navController.startOver(WelcomeRoute) },
            )
        }
        composable<LoginRoute> {
            LoginScreen(
                onLoggedIn = { navController.startOver(WelcomeRoute) },
                onRegister = dropUnlessResumed { navController.navigate(RegisterRoute) },
            )
        }
        composable<RegisterRoute> {
            // Drops a second tap while the pop animates, so it never pops sign-in too.
            val backToLogin = dropUnlessResumed { navController.popBackStack() }
            RegisterScreen(
                // The account exists now, so Back must not return to registration.
                onRegistered = { navController.startOver(OpenStoreRoute) },
                onBack = backToLogin,
                onLogin = backToLogin,
            )
        }
        composable<OpenStoreRoute> {
            // The store status screen (#235) takes the placeholder home's place.
            OpenStoreScreen(
                onOpened = { navController.startOver(WelcomeRoute) },
                onStoreStatus = { navController.startOver(WelcomeRoute) },
            )
        }
        composable<WelcomeRoute> { WelcomeScreen() }
    }
}

/**
 * Opens [route] on an empty back stack, so Back leaves the app instead of
 * returning to a screen of the previous session. Single top, so a sign-out
 * that races the start check opens sign-in once.
 */
private fun <T : Any> NavController.startOver(route: T) {
    navigate(route) {
        popUpTo(graph.id) { inclusive = true }
        launchSingleTop = true
    }
}
