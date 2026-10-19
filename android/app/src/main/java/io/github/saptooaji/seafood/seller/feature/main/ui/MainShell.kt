package io.github.saptooaji.seafood.seller.feature.main.ui

import androidx.annotation.DrawableRes
import androidx.annotation.StringRes
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxHeight
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.selection.selectable
import androidx.compose.foundation.selection.selectableGroup
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.paneTitle
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerAction
import io.github.saptooaji.seafood.seller.core.designsystem.embun.BannerTone
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunBackground
import io.github.saptooaji.seafood.seller.core.designsystem.embun.EmbunColors
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassBanner
import io.github.saptooaji.seafood.seller.core.designsystem.embun.GlassCard
import io.github.saptooaji.seafood.seller.core.designsystem.theme.PillShape
import io.github.saptooaji.seafood.seller.core.designsystem.theme.SeafoodTheme

/** The three parts of the app, in the order of the capsule. */
enum class MainTab(
    @param:StringRes val label: Int,
    @param:DrawableRes val icon: Int,
    val tag: String,
) {
    Products(R.string.main_tab_products, R.drawable.ic_tab_products, MainTags.TAB_PRODUCTS),
    Stock(R.string.main_tab_stock, R.drawable.ic_tab_stock, MainTags.TAB_STOCK),
    Settings(R.string.main_tab_settings, R.drawable.ic_tab_settings, MainTags.TAB_SETTINGS),
}

/** Test tags of the main screens. */
object MainTags {
    const val TAB_PRODUCTS = "main_tab_products"
    const val TAB_STOCK = "main_tab_stock"
    const val TAB_SETTINGS = "main_tab_settings"
    const val STORE_STATUS = "main_store_status"
}

/**
 * The main screens of an active store, or of a pending one that already adds
 * products. The chosen tab survives a rotation. Each tab is a placeholder
 * until its feature arrives (#237, #243, #245).
 *
 * @param storePending shows the "menunggu verifikasi" banner with a link to the status
 */
@Composable
fun MainShell(
    storePending: Boolean,
    onStoreStatus: () -> Unit,
    modifier: Modifier = Modifier,
) {
    var tab by rememberSaveable { mutableStateOf(MainTab.Products) }
    MainShell(tab = tab, onTab = { tab = it }, storePending = storePending, onStoreStatus = onStoreStatus, modifier = modifier)
}

@Composable
fun MainShell(
    tab: MainTab,
    onTab: (MainTab) -> Unit,
    storePending: Boolean,
    onStoreStatus: () -> Unit,
    modifier: Modifier = Modifier,
) {
    EmbunBackground(modifier.fillMaxSize()) {
        Column(
            Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .statusBarsPadding()
                // Room for the capsule floating over the bottom of the content.
                .padding(start = 16.dp, end = 16.dp, top = 26.dp, bottom = 110.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp),
        ) {
            // Each tab brings its own header with its feature, as VD-Produk-Daftar does.
            if (storePending) {
                GlassBanner(
                    text = stringResource(R.string.main_pending_banner),
                    tone = BannerTone.Warn,
                    action =
                        BannerAction(
                            label = stringResource(R.string.main_pending_status),
                            onClick = onStoreStatus,
                            modifier = Modifier.testTag(MainTags.STORE_STATUS),
                        ),
                )
            }
            GlassCard(Modifier.fillMaxWidth()) {
                Text(
                    text = stringResource(R.string.main_tab_coming, stringResource(tab.label)),
                    style = MaterialTheme.typography.bodyLarge,
                    color = EmbunColors.Muted,
                )
            }
        }

        TabCapsule(
            selected = tab,
            onSelect = onTab,
            modifier =
                Modifier
                    .align(Alignment.BottomCenter)
                    .navigationBarsPadding()
                    .padding(start = 28.dp, end = 28.dp, bottom = 16.dp),
        )
    }
}

/**
 * The glass capsule at the bottom: the chosen tab is a dark pill with its
 * name, the others show their icon only (their name is read out).
 */
@Composable
private fun TabCapsule(
    selected: MainTab,
    onSelect: (MainTab) -> Unit,
    modifier: Modifier = Modifier,
) {
    val navigation = stringResource(R.string.main_navigation)
    Row(
        modifier =
            modifier
                .fillMaxWidth()
                .height(60.dp)
                .shadow(16.dp, PillShape, ambientColor = CapsuleShadow, spotColor = CapsuleShadow)
                .background(EmbunColors.GlassStrong, PillShape)
                .border(1.dp, EmbunColors.GlassEdge, PillShape)
                .padding(6.dp)
                .semantics { paneTitle = navigation }
                .selectableGroup(),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        MainTab.entries.forEach { tab ->
            val chosen = tab == selected
            val label = stringResource(tab.label)
            Row(
                modifier =
                    Modifier
                        .weight(if (chosen) CHOSEN_WEIGHT else 1f)
                        .fillMaxHeight()
                        .clip(PillShape)
                        .background(if (chosen) MaterialTheme.colorScheme.onBackground else Color.Transparent)
                        .selectable(selected = chosen, role = Role.Tab, onClick = { onSelect(tab) })
                        .semantics { if (!chosen) contentDescription = label }
                        .testTag(tab.tag),
                horizontalArrangement = Arrangement.spacedBy(6.dp, Alignment.CenterHorizontally),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                val tint = if (chosen) Color.White else MaterialTheme.colorScheme.onBackground
                Icon(painter = painterResource(tab.icon), contentDescription = null, tint = tint, modifier = Modifier.size(20.dp))
                if (chosen) {
                    Text(text = label, style = MaterialTheme.typography.labelMedium.copy(fontSize = 13.sp), color = tint)
                }
            }
        }
    }
}

/** The chosen tab is wider, as in the board (1.5 : 1 : 1). */
private const val CHOSEN_WEIGHT = 1.5f
private val CapsuleShadow = Color(0xFF1B2E25).copy(alpha = 0.12f)

@Preview(widthDp = 390, heightDp = 844)
@Composable
private fun MainShellPreview() {
    SeafoodTheme { Box { MainShell(storePending = true, onStoreStatus = {}) } }
}
