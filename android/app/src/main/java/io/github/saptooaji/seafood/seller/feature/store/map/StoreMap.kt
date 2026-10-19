package io.github.saptooaji.seafood.seller.feature.store.map

import android.annotation.SuppressLint
import android.content.Context
import android.view.MotionEvent
import androidx.compose.runtime.Composable
import androidx.compose.runtime.DisposableEffect
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberUpdatedState
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.LocalLifecycleOwner
import io.github.saptooaji.seafood.seller.BuildConfig
import io.github.saptooaji.seafood.seller.feature.store.domain.GeoPoint
import org.maplibre.android.MapLibre
import org.maplibre.android.camera.CameraPosition
import org.maplibre.android.camera.CameraUpdateFactory
import org.maplibre.android.geometry.LatLng
import org.maplibre.android.maps.MapLibreMap
import org.maplibre.android.maps.MapLibreMapOptions
import org.maplibre.android.maps.MapView
import org.maplibre.android.maps.Style
import org.maplibre.android.module.http.HttpRequestUtil

/** Close enough to tell one stall on a fish market from the next. */
private const val ZOOM = 16.0

/**
 * The MapLibre map under the store pin. The map moves under a pin fixed in
 * the middle (drawn by the caller); each time the camera stops, [onPinMoved]
 * gets the point under the pin. When [move] changes, the map flies to
 * [target], such as the device location.
 */
@Composable
fun StoreMap(
    target: GeoPoint,
    move: Int,
    onPinMoved: (GeoPoint) -> Unit,
    modifier: Modifier = Modifier,
) {
    val context = LocalContext.current
    val lifecycle = LocalLifecycleOwner.current.lifecycle
    val currentOnPinMoved by rememberUpdatedState(onPinMoved)
    var map by remember { mutableStateOf<MapLibreMap?>(null) }
    // Built once; later targets arrive through [move].
    val mapView = remember { createMapView(context, target) }

    DisposableEffect(lifecycle, mapView) {
        val observer =
            LifecycleEventObserver { _, event ->
                when (event) {
                    Lifecycle.Event.ON_START -> mapView.onStart()
                    Lifecycle.Event.ON_RESUME -> mapView.onResume()
                    Lifecycle.Event.ON_PAUSE -> mapView.onPause()
                    Lifecycle.Event.ON_STOP -> mapView.onStop()
                    else -> Unit
                }
            }
        lifecycle.addObserver(observer)
        mapView.getMapAsync { ready ->
            ready.setStyle(Style.Builder().fromJson(MapTiles.styleJson))
            ready.addOnCameraIdleListener {
                ready.cameraPosition.target?.let { currentOnPinMoved(GeoPoint(it.latitude, it.longitude)) }
            }
            map = ready
        }
        onDispose {
            lifecycle.removeObserver(observer)
            mapView.onDestroy()
        }
    }

    LaunchedEffect(map, move) {
        val ready = map ?: return@LaunchedEffect
        if (move > 0) ready.animateCamera(CameraUpdateFactory.newLatLngZoom(LatLng(target.lat, target.lng), ZOOM))
    }

    AndroidView(factory = { mapView }, modifier = modifier)
}

@SuppressLint("ClickableViewAccessibility") // Dragging the map is not a click; the pin has a text alternative.
private fun createMapView(
    context: Context,
    target: GeoPoint,
): MapView {
    MapTilesSetup.ensure(context)
    val options =
        MapLibreMapOptions
            .createFromAttributes(context)
            .camera(
                CameraPosition
                    .Builder()
                    .target(LatLng(target.lat, target.lng))
                    .zoom(ZOOM)
                    .build(),
            ).textureMode(true)
            .logoEnabled(false)
            // The attribution is drawn in Compose, always visible, as the board shows it.
            .attributionEnabled(false)
            .compassEnabled(false)
            .rotateGesturesEnabled(false)
            .tiltGesturesEnabled(false)
    return MapView(context, options).apply {
        onCreate(null)
        // The form scrolls; a finger on the map drags the map, not the form.
        setOnTouchListener { view, event ->
            if (event.actionMasked == MotionEvent.ACTION_DOWN) view.parent?.requestDisallowInterceptTouchEvent(true)
            false
        }
    }
}

/** MapLibre's process-wide setup: done once, before the first map. */
private object MapTilesSetup {
    private var done = false

    fun ensure(context: Context) {
        if (done) return
        MapLibre.getInstance(context.applicationContext)
        HttpRequestUtil.setOkHttpClient(MapTiles.httpClient(BuildConfig.VERSION_NAME))
        done = true
    }
}
