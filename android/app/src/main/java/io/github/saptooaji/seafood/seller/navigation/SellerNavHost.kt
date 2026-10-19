package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.lifecycle.compose.dropUnlessResumed
import androidx.navigation.NavController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.toRoute
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginScreen
import io.github.saptooaji.seafood.seller.feature.auth.ui.RegisterScreen
import io.github.saptooaji.seafood.seller.feature.main.ui.MainShell
import io.github.saptooaji.seafood.seller.feature.start.StartDestination
import io.github.saptooaji.seafood.seller.feature.start.StartScreen
import io.github.saptooaji.seafood.seller.feature.store.ui.OpenStoreScreen
import io.github.saptooaji.seafood.seller.feature.store.ui.StoreStatusScreen
import kotlinx.coroutines.flow.Flow

/**
 * Every screen of the app. Routes are type-safe `@Serializable` objects or
 * classes, one per destination (Routes.kt).
 *
 * Moving between the big parts of the app (sign-in, opening a store, the
 * store status, the main screens) always starts over on an empty back stack.
 * When the session ends (a 401, MOB-6) the seller is sent to sign in with a
 * message saying why; when any seller request finds the store suspended
 * (AC-7), to the store status.
 */
@Composable
fun SellerNavHost(sessionEvents: Flow<SessionEvent>) {
    val navController = rememberNavController()

    LaunchedEffect(navController, sessionEvents) {
        sessionEvents.collect { event ->
            when (event) {
                SessionEvent.SignedOut -> navController.startOver(LoginRoute(sessionExpired = true))
                is SessionEvent.StoreSuspended -> navController.startOver(StoreStatusRoute(serverMessage = event.message))
            }
        }
    }

    NavHost(navController = navController, startDestination = StartRoute) {
        composable<StartRoute> {
            StartScreen(
                onDestination = { destination ->
                    when (destination) {
                        StartDestination.Login -> navController.startOver(LoginRoute())
                        StartDestination.OpenStore -> navController.startOver(OpenStoreRoute)
                        StartDestination.StoreStatus -> navController.startOver(StoreStatusRoute())
                        StartDestination.Main -> navController.startOver(MainRoute())
                        StartDestination.Checking, StartDestination.Offline -> Unit
                    }
                },
            )
        }
        composable<LoginRoute> {
            LoginScreen(
                // The start check reads the store and opens the right screen.
                onLoggedIn = { navController.startOver(StartRoute) },
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
            OpenStoreScreen(
                onOpened = { navController.startOver(StoreStatusRoute()) },
                onStoreStatus = { navController.startOver(StoreStatusRoute()) },
            )
        }
        composable<StoreStatusRoute> {
            StoreStatusScreen(
                onMain = { navController.startOver(MainRoute()) },
                onOpenStore = { navController.startOver(OpenStoreRoute) },
                onSignedOut = { navController.startOver(LoginRoute()) },
                onStartProducts = { navController.startOver(MainRoute(storePending = true)) },
            )
        }
        composable<MainRoute> { entry ->
            MainShell(
                storePending = entry.toRoute<MainRoute>().storePending,
                onStoreStatus = { navController.startOver(StoreStatusRoute()) },
            )
        }
    }
}

/**
 * Opens [route] on an empty back stack, so Back leaves the app instead of
 * returning to a screen of the previous session. Single top, so a sign-out
 * that races the start check opens sign-in once.
 */
internal fun <T : Any> NavController.startOver(route: T) {
    navigate(route) {
        popUpTo(graph.id) { inclusive = true }
        launchSingleTop = true
    }
}
