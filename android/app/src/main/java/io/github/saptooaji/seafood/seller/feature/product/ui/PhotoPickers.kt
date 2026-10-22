package io.github.saptooaji.seafood.seller.feature.product.ui

import android.content.ActivityNotFoundException
import android.net.Uri
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.PickVisualMediaRequest
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.FileProvider
import java.io.File
import java.util.UUID

/**
 * Opens the camera app or the Photo Picker and hands the picked photo's uri
 * to [onPicked] (#241). Neither needs a permission: the Photo Picker grants
 * the one photo, and the camera app writes into a cache file shared through
 * the app's private FileProvider. A camera shot left unused is deleted.
 */
@Composable
fun rememberPhotoPicker(onPicked: (String) -> Unit): (PhotoSource) -> Unit {
    val context = LocalContext.current
    // Kept across process death: the camera app may outlive the app.
    var shot by rememberSaveable { mutableStateOf<String?>(null) }
    val camera =
        rememberLauncherForActivityResult(ActivityResultContracts.TakePicture()) { taken ->
            val file = shot?.let(::File)
            shot = null
            if (taken && file != null && file.length() > 0) onPicked(Uri.fromFile(file).toString()) else file?.delete()
        }
    val gallery =
        rememberLauncherForActivityResult(ActivityResultContracts.PickVisualMedia()) { uri ->
            uri?.let { onPicked(it.toString()) }
        }
    return { source ->
        when (source) {
            PhotoSource.Gallery -> gallery.launch(PickVisualMediaRequest(ActivityResultContracts.PickVisualMedia.ImageOnly))
            PhotoSource.Camera -> {
                val file = File(File(context.cacheDir, PHOTO_FOLDER).apply { mkdirs() }, "shot-${UUID.randomUUID()}.jpg")
                shot = file.path
                try {
                    camera.launch(FileProvider.getUriForFile(context, "${context.packageName}$AUTHORITY_SUFFIX", file))
                } catch (_: ActivityNotFoundException) {
                    // No camera app: the gallery still works.
                    shot = null
                }
            }
        }
    }
}

/** The folder of `xml/photo_paths`, shared with the camera app. */
private const val PHOTO_FOLDER = "photos"

/** As the provider's `android:authorities` in the manifest. */
private const val AUTHORITY_SUFFIX = ".photos"
