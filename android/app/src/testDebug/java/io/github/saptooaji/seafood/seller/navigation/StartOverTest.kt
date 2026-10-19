package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.material3.Text
import androidx.compose.ui.test.junit4.v2.createComposeRule
import androidx.navigation.NavHostController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import kotlinx.serialization.Serializable
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner

/**
 * Signing out, a refused token, and every move between the big parts of the
 * app (sign-in, store status, main screens) start over on an empty back
 * stack, so Back never returns to a screen of the previous session (MOB-6).
 */
@RunWith(RobolectricTestRunner::class)
class StartOverTest {
    @get:Rule
    val compose = createComposeRule()

    @Serializable
    data object First

    @Serializable
    data object Second

    @Serializable
    data object SignIn

    @Test
    fun startingOverLeavesOnlyTheNewScreen() {
        lateinit var navController: NavHostController
        compose.setContent {
            navController = rememberNavController()
            NavHost(navController = navController, startDestination = First) {
                composable<First> { Text("first") }
                composable<Second> { Text("second") }
                composable<SignIn> { Text("sign in") }
            }
        }

        compose.runOnIdle { navController.navigate(Second) }
        compose.runOnIdle { navController.startOver(SignIn) }

        compose.runOnIdle {
            val screens = navController.currentBackStack.value.filter { it.destination.route != null && it.destination.parent != null }
            assertEquals(1, screens.size)
            // Back has nothing to return to: the app closes instead.
            assertNull(navController.previousBackStackEntry)
        }
    }
}
