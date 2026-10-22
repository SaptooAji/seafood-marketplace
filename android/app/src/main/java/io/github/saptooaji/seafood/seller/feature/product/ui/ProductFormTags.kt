package io.github.saptooaji.seafood.seller.feature.product.ui

import io.github.saptooaji.seafood.seller.feature.product.domain.ProductCondition
import io.github.saptooaji.seafood.seller.feature.product.domain.SalesMode
import io.github.saptooaji.seafood.seller.feature.product.domain.VariantUnit

/** Test tags of the product form and its sheets. */
@Suppress("TooManyFunctions") // One tag builder per repeated element of the form.
object ProductFormTags {
    const val BACK = "product_form_back"
    const val CATEGORY = "product_form_category"
    const val NAME = "product_form_name"
    const val DESCRIPTION = "product_form_description"
    const val SEO = "product_form_seo"
    const val QUOTA = "product_form_quota"
    const val LEAD_TIME = "product_form_lead_time"
    const val CUTOFF = "product_form_cutoff"
    const val SHELF_LIFE = "product_form_shelf_life"
    const val STORAGE = "product_form_storage"
    const val VARIANT_NAME = "product_form_variant_name"
    const val VARIANT_PRICE = "product_form_variant_price"
    const val VARIANT_MIN_QTY = "product_form_variant_min_qty"
    const val VARIANT_STOCK = "product_form_variant_stock"
    const val ACTIVE = "product_form_active"
    const val DELETE = "product_form_delete"
    const val SAVE = "product_form_save"
    const val RETRY = "product_form_retry"
    const val CONFIRM_DELETE = "product_form_confirm_delete"
    const val CANCEL_DELETE = "product_form_cancel_delete"
    const val CONFIRM_DISCARD = "product_form_confirm_discard"
    const val KEEP_EDITING = "product_form_keep_editing"
    const val BACK_TO_LIST = "product_form_back_to_list"
    const val CATEGORY_CLOSE = "product_category_close"
    const val ADD_VARIANT = "product_form_add_variant"
    const val VARIANT_SHEET_NAME = "variant_sheet_name"
    const val VARIANT_SHEET_PRICE = "variant_sheet_price"
    const val VARIANT_SHEET_MIN_QTY = "variant_sheet_min_qty"
    const val VARIANT_SHEET_STOCK = "variant_sheet_stock"
    const val VARIANT_SHEET_ACTIVE = "variant_sheet_active"
    const val VARIANT_SHEET_DELETE = "variant_sheet_delete"
    const val VARIANT_SHEET_SAVE = "variant_sheet_save"
    const val CONFIRM_DELETE_VARIANT = "variant_confirm_delete"
    const val CANCEL_DELETE_VARIANT = "variant_cancel_delete"
    const val ADD_PHOTO = "product_form_add_photo"
    const val PHOTO_FROM_CAMERA = "photo_source_camera"
    const val PHOTO_FROM_GALLERY = "photo_source_gallery"
    const val PHOTO_RESUME = "product_form_photo_resume"
    const val PHOTO_MAKE_MAIN = "photo_make_main"
    const val PHOTO_DELETE = "photo_delete"
    const val CONFIRM_DELETE_PHOTO = "photo_confirm_delete"
    const val CANCEL_DELETE_PHOTO = "photo_cancel_delete"
    const val SUGGEST = "product_form_suggest"
    const val SUGGESTION_USE = "suggestion_use"
    const val SUGGESTION_CANCEL = "suggestion_cancel"
    const val SUGGESTION_RETRY = "suggestion_retry"
    const val SUGGESTION_CLOSE = "suggestion_close"

    fun variantRow(id: Long) = "product_form_variant_$id"

    fun photo(key: String) = "product_form_photo_$key"

    fun photoRetry(key: String) = "product_form_photo_retry_$key"

    fun photoRemove(key: String) = "product_form_photo_remove_$key"

    fun sheetUnit(unit: VariantUnit) = "variant_sheet_unit_${unit.value}"

    fun attribute(id: Long) = "product_form_attribute_$id"

    fun attributeOption(
        id: Long,
        option: String,
    ) = "product_form_attribute_${id}_$option"

    fun categoryRow(id: Long) = "product_category_$id"

    fun condition(condition: ProductCondition) = "product_form_condition_${condition.value}"

    fun salesMode(mode: SalesMode) = "product_form_sales_mode_${mode.value}"

    fun unit(unit: VariantUnit) = "product_form_unit_${unit.value}"
}
