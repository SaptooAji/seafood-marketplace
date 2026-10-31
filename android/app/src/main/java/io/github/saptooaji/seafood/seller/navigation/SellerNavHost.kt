package io.github.saptooaji.seafood.seller.navigation

import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.compose.dropUnlessResumed
import androidx.navigation.NavBackStackEntry
import androidx.navigation.NavController
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.rememberNavController
import androidx.navigation.toRoute
import io.github.saptooaji.seafood.seller.core.session.SessionEvent
import io.github.saptooaji.seafood.seller.feature.auth.ui.LoginScreen
import io.github.saptooaji.seafood.seller.feature.auth.ui.RegisterScreen
import io.github.saptooaji.seafood.seller.feature.main.ui.MainShell
import io.github.saptooaji.seafood.seller.feature.main.ui.MainTab
import io.github.saptooaji.seafood.seller.feature.product.photo.PhotoCropResult
import io.github.saptooaji.seafood.seller.feature.product.photo.PhotoCropScreen
import io.github.saptooaji.seafood.seller.feature.product.ui.ProductFormResult
import io.github.saptooaji.seafood.seller.feature.product.ui.ProductFormScreen
import io.github.saptooaji.seafood.seller.feature.product.ui.ProductListScreen
import io.github.saptooaji.seafood.seller.feature.settings.ui.EditStoreResult
import io.github.saptooaji.seafood.seller.feature.settings.ui.EditStoreScreen
import io.github.saptooaji.seafood.seller.feature.settings.ui.SettingsScreen
import io.github.saptooaji.seafood.seller.feature.start.StartDestination
import io.github.saptooaji.seafood.seller.feature.start.StartScreen
import io.github.saptooaji.seafood.seller.feature.stock.ui.StockBookScreen
import io.github.saptooaji.seafood.seller.feature.stock.ui.StockTodayScreen
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
                        StartDestination.Checking, StartDestination.Offline, StartDestination.ServerDown -> Unit
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
        composable<MainRoute> { entry -> MainDestination(navController, entry, entry.toRoute<MainRoute>().storePending) }
        composable<StockTodayRoute> { entry -> MainDestination(navController, entry, storePending = false, initialTab = MainTab.Stock) }
        composable<StockBookRoute> {
            StockBookScreen(
                onBack = dropUnlessResumed { navController.popBackStack() },
                onRecorded = {
                    navController.previousBackStackEntry?.savedStateHandle?.let { handle ->
                        handle[KEY_STOCK_CHANGES] = (handle.get<Int>(KEY_STOCK_CHANGES) ?: 0) + 1
                    }
                },
            )
        }
        composable<StoreEditRoute> {
            EditStoreScreen(
                onDone = { result ->
                    // Pengaturan reads the store again, or says why it could not be saved.
                    if (result != null) navController.previousBackStackEntry?.savedStateHandle?.set(KEY_STORE_EDITED, result.name)
                    navController.popBackStack()
                },
            )
        }
        composable<ProductAddRoute> { entry -> ProductFormDestination(navController, entry) }
        composable<ProductEditRoute> { entry -> ProductFormDestination(navController, entry) }
        composable<PhotoCropRoute> {
            PhotoCropScreen(
                onDone = { result ->
                    if (result is PhotoCropResult.Done) {
                        navController.previousBackStackEntry?.savedStateHandle?.set(
                            KEY_PREPARED_PHOTO,
                            result.path,
                        )
                    }
                    navController.popBackStack()
                },
            )
        }
    }
}

/** The main screens: products, stock, and settings in one tab capsule. */
@Composable
private fun MainDestination(
    navController: NavController,
    entry: NavBackStackEntry,
    storePending: Boolean,
    initialTab: MainTab = MainTab.Products,
) {
    val productChanges by entry.savedStateHandle.getStateFlow(KEY_PRODUCT_CHANGES, 0).collectAsStateWithLifecycle()
    val stockChanges by entry.savedStateHandle.getStateFlow(KEY_STOCK_CHANGES, 0).collectAsStateWithLifecycle()
    val storeEdited by entry.savedStateHandle.getStateFlow<String?>(KEY_STORE_EDITED, null).collectAsStateWithLifecycle()
    MainShell(
        storePending = storePending,
        onStoreStatus = { navController.startOver(StoreStatusRoute()) },
        initialTab = initialTab,
        productsTab = { banner ->
            ProductListScreen(
                onAddProduct = dropUnlessResumed { navController.navigate(ProductAddRoute) },
                onOpenProduct = { id -> if (entry.isResumed()) navController.navigate(ProductEditRoute(id)) },
                banner = banner,
                changes = productChanges,
            )
        },
        stockTab = { banner, openProducts ->
            StockTodayScreen(
                onOpenProducts = openProducts,
                onOpenBook = dropUnlessResumed { navController.navigate(StockBookRoute) },
                banner = banner,
                changes = stockChanges,
            )
        },
        settingsTab = { onModalChange ->
            SettingsScreen(
                onEditStore = dropUnlessResumed { navController.navigate(StoreEditRoute) },
                // The shipping methods screen arrives with #246.
                onShippingMethods = {},
                onSignedOut = { navController.startOver(LoginRoute()) },
                storeEdited = storeEdited?.let { name -> EditStoreResult.entries.firstOrNull { it.name == name } },
                onStoreEditedTaken = { entry.savedStateHandle[KEY_STORE_EDITED] = null },
                onModalChange = onModalChange,
            )
        },
    )
}

/**
 * The product form over the main screens. Once it is saved or the product
 * deleted, the list below is told to refresh before the form closes.
 */
@Composable
private fun ProductFormDestination(
    navController: NavController,
    entry: NavBackStackEntry,
) {
    val preparedPhoto by entry.savedStateHandle.getStateFlow<String?>(KEY_PREPARED_PHOTO, null).collectAsStateWithLifecycle()
    ProductFormScreen(
        onCropPhoto = { uri -> if (entry.isResumed()) navController.navigate(PhotoCropRoute(uri)) },
        preparedPhoto = preparedPhoto,
        onPreparedPhotoTaken = { entry.savedStateHandle[KEY_PREPARED_PHOTO] = null },
        onDone = { result ->
            if (result != ProductFormResult.Left) {
                navController.previousBackStackEntry?.savedStateHandle?.let { handle ->
                    handle[KEY_PRODUCT_CHANGES] = (handle.get<Int>(KEY_PRODUCT_CHANGES) ?: 0) + 1
                }
            }
            navController.popBackStack()
        },
    )
}

/** Counts the products saved or deleted, in the main screens' SavedStateHandle. */
private const val KEY_PRODUCT_CHANGES = "product_changes"

/** Counts the changes recorded in the stock book, in the main screens' SavedStateHandle. */
private const val KEY_STOCK_CHANGES = "stock_changes"

/** How the store form ended, as an [EditStoreResult] name, in the main screens' SavedStateHandle. */
private const val KEY_STORE_EDITED = "store_edited"

/** The prepared photo the crop screen hands back, in the form's SavedStateHandle. */
private const val KEY_PREPARED_PHOTO = "prepared_photo"

private fun NavBackStackEntry.isResumed() = lifecycle.currentState == Lifecycle.State.RESUMED

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
