package io.github.saptooaji.seafood.seller.feature.product

import androidx.compose.ui.test.SemanticsNodeInteraction
import androidx.compose.ui.test.assertIsDisplayed
import androidx.compose.ui.test.assertTextContains
import androidx.compose.ui.test.hasTestTag
import androidx.compose.ui.test.hasText
import androidx.compose.ui.test.junit4.v2.createAndroidComposeRule
import androidx.compose.ui.test.onNodeWithTag
import androidx.compose.ui.test.performClick
import androidx.compose.ui.test.performScrollTo
import androidx.compose.ui.test.performTextInput
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.testing.BindValue
import dagger.hilt.android.testing.HiltAndroidRule
import dagger.hilt.android.testing.HiltAndroidTest
import dagger.hilt.android.testing.HiltTestApplication
import dagger.hilt.android.testing.UninstallModules
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.MainActivity
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.network.DeviceNetwork
import io.github.saptooaji.seafood.seller.core.network.FakeTokenStore
import io.github.saptooaji.seafood.seller.core.network.HttpClients
import io.github.saptooaji.seafood.seller.core.network.NetworkModule
import io.github.saptooaji.seafood.seller.core.session.SessionEvents
import io.github.saptooaji.seafood.seller.core.session.TokenStore
import io.github.saptooaji.seafood.seller.core.session.data.SessionModule
import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit
import io.github.saptooaji.seafood.seller.feature.product.ui.ProductFormTags
import io.github.saptooaji.seafood.seller.feature.product.ui.ProductListTags
import kotlinx.serialization.json.Json
import kotlinx.serialization.json.int
import kotlinx.serialization.json.jsonArray
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import kotlinx.serialization.json.long
import okhttp3.HttpUrl
import okhttp3.OkHttpClient
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertNull
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith
import org.robolectric.RobolectricTestRunner
import org.robolectric.annotation.Config
import retrofit2.Retrofit
import javax.inject.Singleton

/**
 * Adding a product the way a seller does (#242): the real app, its Hilt
 * graph, screens, view models, and repositories, against [FakeSellerServer]
 * instead of the backend. Only the network setup and the token store are
 * swapped. The test APIs are the same as an instrumented test's, so it can
 * move to `androidTest` and run on an emulator without being rewritten.
 * Photos are left out; their upload is tested in #241 and #282.
 */
@HiltAndroidTest
@UninstallModules(NetworkModule::class, SessionModule::class)
// A common phone screen: Robolectric's default (320 x 470 dp) is shorter than
// any phone, and the empty list's button falls under the tab capsule there.
@Config(application = HiltTestApplication::class, qualifiers = "w411dp-h891dp")
@RunWith(RobolectricTestRunner::class)
class AddProductFlowTest {
    private val server = FakeSellerServer()

    @get:Rule(order = 0)
    val hilt = HiltAndroidRule(this)

    @get:Rule(order = 1)
    val compose = createAndroidComposeRule<MainActivity>()

    @BindValue
    @JvmField
    val baseUrl: HttpUrl = server.baseUrl

    /** A seller who signed in before; the token is a test one. */
    @BindValue
    @JvmField
    val tokenStore: TokenStore = FakeTokenStore().apply { state.value = FakeSellerServer.TOKEN }

    /** [NetworkModule] without the debug logger, always online, talking to [server]. */
    @Module
    @InstallIn(SingletonComponent::class)
    object FakeServerNetworkModule {
        @Provides
        fun json(): Json = HttpClients.json

        @Provides
        fun deviceNetwork(): DeviceNetwork = DeviceNetwork { true }

        @Provides
        @Singleton
        fun okHttpClient(
            baseUrl: HttpUrl,
            tokenStore: TokenStore,
            sessionEvents: SessionEvents,
            deviceNetwork: DeviceNetwork,
        ): OkHttpClient = HttpClients.okHttpClient(baseUrl, tokenStore, sessionEvents, debugLogger = null, deviceNetwork = deviceNetwork)

        @Provides
        @Singleton
        fun retrofit(
            baseUrl: HttpUrl,
            client: OkHttpClient,
        ): Retrofit = HttpClients.retrofit(baseUrl, client)
    }

    @After
    fun stopServer() = server.close()

    @Test
    fun aNewProductIsSavedAndShowsInTheList() {
        openAddForm()
        fillForm()

        compose.onNodeWithTag(ProductFormTags.SAVE).performClick()

        val row = waitFor(ProductListTags.row(FakeSellerServer.CREATED_ID))
        row.assertTextContains("Kepiting Bakau Super", substring = true)
        row.assertTextContains(compose.activity.getString(R.string.product_price, "Rp185.000", "kg"), substring = true)
        val sent = Json.parseToJsonElement(requireNotNull(server.createdBody)).jsonObject
        assertEquals(2L, sent.getValue("category_id").jsonPrimitive.long)
        assertEquals("Kepiting Bakau Super", sent.getValue("name").jsonPrimitive.content)
        assertEquals("live", sent.getValue("condition").jsonPrimitive.content)
        assertEquals("ready_stock", sent.getValue("sales_mode").jsonPrimitive.content)
        assertEquals(
            "500",
            sent
                .getValue("attributes")
                .jsonObject
                .getValue("10")
                .jsonPrimitive.content,
        )
        val variant =
            sent
                .getValue("variants")
                .jsonArray
                .single()
                .jsonObject
        assertEquals("Super 500 g", variant.getValue("name").jsonPrimitive.content)
        assertEquals(185_000, variant.getValue("price").jsonPrimitive.int)
        assertEquals("kg", variant.getValue("unit").jsonPrimitive.content)
    }

    @Test
    fun aNameTheServerRefusesShowsUnderTheFieldAndTheFormStays() {
        server.refuseName = true
        openAddForm()
        fillForm()

        compose.onNodeWithTag(ProductFormTags.SAVE).performClick()

        compose.waitUntil(TIMEOUT) { compose.onAllNodes(hasText(FakeSellerServer.NAME_REFUSED)).fetchSemanticsNodes().isNotEmpty() }
        compose.onNode(hasText(FakeSellerServer.NAME_REFUSED)).performScrollTo().assertIsDisplayed()
        compose.onNodeWithTag(ProductFormTags.NAME).assertTextContains("Kepiting Bakau Super")
        compose.onNodeWithTag(ProductListTags.LIST).assertDoesNotExist()
        assertNull(server.createdBody)
    }

    @Test
    fun aRotationHalfwayKeepsWhatWasTyped() {
        openAddForm()
        pickBakau()
        type(ProductFormTags.NAME, "Kepiting Bakau Super")
        type(ProductFormTags.VARIANT_PRICE, "185000")

        compose.activityRule.scenario.recreate()

        waitFor(ProductFormTags.NAME).assertTextContains("Kepiting Bakau Super")
        compose.onNodeWithTag(ProductFormTags.CATEGORY).performScrollTo().assertTextContains("Bakau", substring = true)
        compose.onNodeWithTag(ProductFormTags.VARIANT_PRICE).performScrollTo().assertTextContains("185000", substring = true)
    }

    /** From the empty list, as a new store sees it, to the add form with its categories loaded. */
    private fun openAddForm() {
        waitFor(ProductListTags.ADD_FIRST).performClick()
        waitFor(ProductFormTags.CATEGORY)
    }

    private fun pickBakau() {
        compose.onNodeWithTag(ProductFormTags.CATEGORY).performScrollTo().performClick()
        // The parent opens its children; only the leaf can be picked.
        compose.onNodeWithTag(ProductFormTags.categoryRow(1)).performClick()
        compose.onNodeWithTag(ProductFormTags.categoryRow(2)).performClick()
    }

    /** Everything a ready-stock product needs, photos aside. */
    private fun fillForm() {
        pickBakau()
        type(ProductFormTags.attribute(WEIGHT_ID), "500")
        type(ProductFormTags.NAME, "Kepiting Bakau Super")
        type(ProductFormTags.DESCRIPTION, "Kepiting bakau hidup dari tambak Muara Angke.")
        click(ProductFormTags.condition(ProductCondition.Live))
        click(ProductFormTags.salesMode(SalesMode.ReadyStock))
        type(ProductFormTags.VARIANT_NAME, "Super 500 g")
        type(ProductFormTags.VARIANT_PRICE, "185000")
        click(ProductFormTags.unit(VariantUnit.Kg))
        type(ProductFormTags.VARIANT_STOCK, "18")
    }

    private fun type(
        tag: String,
        text: String,
    ) {
        compose.onNodeWithTag(tag).performScrollTo().performTextInput(text)
    }

    private fun click(tag: String) {
        compose.onNodeWithTag(tag).performScrollTo().performClick()
    }

    /** The screens load over a real socket, so the test waits for them. */
    private fun waitFor(tag: String): SemanticsNodeInteraction {
        compose.waitUntil(TIMEOUT) { compose.onAllNodes(hasTestTag(tag)).fetchSemanticsNodes().isNotEmpty() }
        return compose.onNodeWithTag(tag)
    }

    private companion object {
        const val TIMEOUT = 10_000L

        /** "Berat per ekor" in [FakeSellerServer]'s category tree. */
        const val WEIGHT_ID = 10L
    }
}
