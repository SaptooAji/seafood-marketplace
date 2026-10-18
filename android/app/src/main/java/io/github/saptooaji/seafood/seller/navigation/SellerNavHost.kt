package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import io.github.saptooaji.seafood.seller.feature.welcome.ui.WelcomeScreen

/**
 * Every screen of the app. Routes are type-safe `@Serializable` objects or
 * classes, one per destination.
 */
@Composable
fun SellerNavHost() {
    val navController = rememberNavController()
    NavHost(navController = navController, startDestination = WelcomeRoute) {
        composable<WelcomeRoute> { WelcomeScreen() }
    }
}
