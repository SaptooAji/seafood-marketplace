package io.github.saptooaji.seafood.seller.feature.product.photo

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.Binds
import dagger.Module
import dagger.hilt.InstallIn
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.components.SingletonComponent
import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import java.io.File
import javax.inject.Inject

/** How the crop screen was left. */
sealed interface PhotoCropResult {
    /** [path] is the prepared JPEG in the cache, for the form to upload. */
    data class Done(
        val path: String,
    ) : PhotoCropResult

    data object Cancelled : PhotoCropResult
}

data class PhotoCropUiState(
    val isLoading: Boolean = true,
    /** The cache copy of the picked photo, shown and cut. */
    val sourcePath: String? = null,
    val crop: CropState? = null,
    val isPreparing: Boolean = false,
    val error: UiText? = null,
    val result: PhotoCropResult? = null,
) {
    val isTooSmall: Boolean get() = crop?.isTooSmall() == true

    val canUse: Boolean get() = crop != null && !isTooSmall && !isPreparing
}

sealed interface PhotoCropEvent {
    data class AspectPicked(
        val aspect: CropAspect,
    ) : PhotoCropEvent

    data object Rotate : PhotoCropEvent

    data class Zoom(
        val factor: Float,
    ) : PhotoCropEvent

    /** Moved by photo pixels; the screen converts from its own. */
    data class Pan(
        val dx: Float,
        val dy: Float,
    ) : PhotoCropEvent

    data object Use : PhotoCropEvent

    data object Cancel : PhotoCropEvent
}

/**
 * The crop screen (#241, boards VD-Potong-Foto). The picked photo is copied
 * into the cache once, so a recreated screen still has it after the
 * picker's permission is gone; the copy is deleted when the screen is left.
 */
@HiltViewModel
class PhotoCropViewModel
    @Inject
    constructor(
        private val preparer: PhotoPreparer,
        private val savedStateHandle: SavedStateHandle,
    ) : ViewModel() {
        private val _uiState = MutableStateFlow(PhotoCropUiState())
        val uiState: StateFlow<PhotoCropUiState> = _uiState.asStateFlow()

        init {
            load()
        }

        fun onEvent(event: PhotoCropEvent) {
            when (event) {
                is PhotoCropEvent.AspectPicked -> changeCrop { it.withAspect(event.aspect) }
                PhotoCropEvent.Rotate -> changeCrop { it.rotated() }
                is PhotoCropEvent.Zoom -> changeCrop(store = false) { it.zoomedBy(event.factor) }
                is PhotoCropEvent.Pan -> changeCrop(store = false) { it.pannedBy(event.dx, event.dy) }
                PhotoCropEvent.Use -> use()
                PhotoCropEvent.Cancel -> leave(PhotoCropResult.Cancelled)
            }
        }

        private fun load() {
            viewModelScope.launch {
                val source =
                    savedStateHandle.get<String>(KEY_SOURCE_PATH)?.let(::File)?.takeIf { it.exists() }
                        ?: savedStateHandle.get<String>(KEY_URI)?.let { preparer.import(it) }
                val size = source?.let { preparer.uprightSize(it) }
                if (source == null || size == null) {
                    _uiState.update { it.copy(isLoading = false, error = UiText.Resource(R.string.photo_crop_unreadable)) }
                    return@launch
                }
                savedStateHandle[KEY_SOURCE_PATH] = source.path
                var crop = CropState.of(size.width, size.height)
                savedStateHandle.get<String>(KEY_ASPECT)?.let { name -> CropAspect.entries.firstOrNull { it.name == name } }?.let {
                    crop = crop.withAspect(it)
                }
                repeat(savedStateHandle.get<Int>(KEY_TURNS) ?: 0) { crop = crop.rotated() }
                _uiState.update { it.copy(isLoading = false, sourcePath = source.path, crop = crop) }
            }
        }

        /** Zoom and pan are not kept: a recreated screen starts at the full frame. */
        private fun changeCrop(
            store: Boolean = true,
            change: (CropState) -> CropState,
        ) {
            val crop = _uiState.value.crop ?: return
            if (_uiState.value.isPreparing) return
            val changed = change(crop)
            _uiState.update { it.copy(crop = changed, error = null) }
            if (store) {
                savedStateHandle[KEY_ASPECT] = changed.aspect.name
                savedStateHandle[KEY_TURNS] = changed.quarterTurns
            }
        }

        private fun use() {
            val state = _uiState.value
            if (!state.canUse) return
            val crop = requireNotNull(state.crop)
            val source = File(requireNotNull(state.sourcePath))
            _uiState.update { it.copy(isPreparing = true, error = null) }
            viewModelScope.launch {
                when (val prepared = preparer.prepare(source, crop.rect(), crop.quarterTurns)) {
                    is PreparedPhoto.Ready -> leave(PhotoCropResult.Done(prepared.file.path))
                    else ->
                        _uiState.update {
                            // Kept, so the seller can try another crop or another photo.
                            it.copy(isPreparing = false, error = UiText.Resource(R.string.photo_crop_unreadable))
                        }
                }
            }
        }

        private fun leave(result: PhotoCropResult) {
            _uiState.value.sourcePath?.let { File(it).delete() }
            savedStateHandle.remove<String>(KEY_SOURCE_PATH)
            _uiState.update { it.copy(isPreparing = false, result = result) }
        }

        companion object {
            /** The route's argument: the picked photo's uri, from the Photo Picker or the camera. */
            const val KEY_URI = "source"
            private const val KEY_SOURCE_PATH = "crop_source_path"
            private const val KEY_ASPECT = "crop_aspect"
            private const val KEY_TURNS = "crop_turns"
        }
    }

@Module
@InstallIn(SingletonComponent::class)
interface PhotoModule {
    @Binds
    fun photoPreparer(preparer: PreparePhoto): PhotoPreparer
}
