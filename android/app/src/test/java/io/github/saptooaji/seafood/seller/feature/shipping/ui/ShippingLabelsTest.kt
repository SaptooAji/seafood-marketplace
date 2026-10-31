package io.github.saptooaji.seafood.seller.feature.shipping.ui

import io.github.saptooaji.seafood.seller.R
import io.github.saptooaji.seafood.seller.core.util.UiText
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OJOL
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_FLAT
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.OWN_PER_KM
import io.github.saptooaji.seafood.seller.feature.shipping.FakeShippingMethodRepository.Companion.PICKUP
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingFeeType
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingMethod
import io.github.saptooaji.seafood.seller.feature.shipping.domain.ShippingType
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Test
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.CsvSource
import java.math.BigDecimal

/** What the rows of Cara terima pesanan say, the buyer preview, and how fields are read. */
class ShippingLabelsTest {
    // --- Row summaries ----------------------------------------------------

    @Test
    fun `pickup is free`() {
        assertEquals(UiText.Resource(R.string.shipping_summary_pickup), ShippingLabels.summary(PICKUP))
    }

    @Test
    fun `own delivery per km names the fee per km, the minimum, and the radius`() {
        assertEquals(
            UiText.Resource(R.string.shipping_summary_per_km, listOf("Rp3.000", "Rp10.000", "20")),
            ShippingLabels.summary(OWN_PER_KM),
        )
    }

    @Test
    fun `own delivery flat names the fee and the radius`() {
        assertEquals(UiText.Resource(R.string.shipping_summary_flat, listOf("Rp10.000", "10")), ShippingLabels.summary(OWN_FLAT))
    }

    @Test
    fun `a radius with decimals is shown with a comma and no trailing zero`() {
        assertEquals(
            UiText.Resource(R.string.shipping_summary_flat, listOf("Rp10.000", "7,5")),
            ShippingLabels.summary(OWN_FLAT.copy(maxRadiusKm = BigDecimal("7.50"))),
        )
    }

    @Test
    fun `ojol and expedition say the fee is filled in per order`() {
        assertEquals(UiText.Resource(R.string.shipping_summary_ojol), ShippingLabels.summary(OJOL))
        assertEquals(
            UiText.Resource(R.string.shipping_summary_expedition),
            ShippingLabels.summary(OJOL.copy(type = ShippingType.Expedition)),
        )
    }

    @Test
    fun `a kind not added yet is described by what it would do`() {
        assertEquals(UiText.Resource(R.string.shipping_about_own_delivery), ShippingLabels.about(ShippingType.OwnDelivery))
        assertEquals(UiText.Resource(R.string.shipping_summary_ojol), ShippingLabels.about(ShippingType.Ojol))
        assertEquals(UiText.Resource(R.string.shipping_summary_expedition), ShippingLabels.about(ShippingType.Expedition))
    }

    // --- Buyer preview ----------------------------------------------------

    @ParameterizedTest(name = "{0} km costs {1}")
    @CsvSource(
        "2, 10000",
        "6, 18000",
        "15, 45000",
        "20, 60000",
    )
    fun `per km is the distance times the fee, never below the minimum`(
        km: Int,
        fee: Long,
    ) {
        assertEquals(fee, ShippingPreview.fee(OWN_PER_KM.preview(), km))
    }

    @Test
    fun `a buyer beyond the radius cannot pick it`() {
        assertNull(ShippingPreview.fee(OWN_PER_KM.preview(), 25))
        assertNull(ShippingPreview.fee(OWN_FLAT.preview(), 15))
    }

    @Test
    fun `flat is the same for every buyer within the radius`() {
        assertEquals(10_000L, ShippingPreview.fee(OWN_FLAT.preview(), 2))
        assertEquals(10_000L, ShippingPreview.fee(OWN_FLAT.preview(), 10))
    }

    @Test
    fun `a radius with decimals still takes a buyer just inside it`() {
        val form = OwnDeliveryForm(radius = "7,5", feeType = ShippingFeeType.Flat, flatFee = "5000")

        assertEquals(5_000L, ShippingPreview.fee(form, 7))
        assertNull(ShippingPreview.fee(form, 8))
    }

    @Test
    fun `a form that is not filled in yet shows no price`() {
        assertNull(ShippingPreview.fee(OwnDeliveryForm(radius = "", feeType = ShippingFeeType.Flat, flatFee = "5000"), 2))
        assertNull(ShippingPreview.fee(OwnDeliveryForm(radius = "10", feeType = ShippingFeeType.PerKm, feePerKm = ""), 2))
    }

    @Test
    fun `the preview shows the distances of the board`() {
        assertEquals(listOf(2, 6, 15, 25), ShippingPreview.DISTANCES)
    }

    // --- Reading the fields -----------------------------------------------

    @ParameterizedTest(name = "\"{0}\" is {1}")
    @CsvSource(
        "10, 10",
        "'7,5', 7.5",
        "7.5, 7.5",
        "' 12 ', 12",
        "'9999,99', 9999.99",
    )
    fun `a radius is read with a comma or a dot`(
        typed: String,
        sent: String,
    ) {
        assertEquals(BigDecimal(sent), ShippingInput.radius(typed))
    }

    @ParameterizedTest(name = "\"{0}\" is refused")
    @CsvSource("''", "jauh", "'1,234'", "-3", "'1,2,3'")
    fun `a radius that is not a number with two decimals at most is refused`(typed: String) {
        assertNull(ShippingInput.radius(typed))
    }

    @ParameterizedTest(name = "\"{0}\" is {1}")
    @CsvSource(
        "10000, 10000",
        "10.000, 10000",
        "'Rp 3.000', 3000",
        "0, 0",
    )
    fun `a fee is read as whole rupiah, with or without thousands dots`(
        typed: String,
        amount: Long,
    ) {
        assertEquals(amount, ShippingInput.fee(typed))
    }

    @ParameterizedTest(name = "\"{0}\" is refused")
    @CsvSource("''", "'1,5'", "abc", "-500")
    fun `a fee that is not whole rupiah is refused`(typed: String) {
        assertNull(ShippingInput.fee(typed))
    }

    @Test
    fun `a method fills the form with plain digits and a comma radius`() {
        val form = OwnDeliveryForm.from(OWN_PER_KM.copy(maxRadiusKm = BigDecimal("12.50")))

        assertEquals("12,5", form.radius)
        assertEquals("3000", form.feePerKm)
        assertEquals("10000", form.minFee)
    }

    private fun ShippingMethod.preview() = OwnDeliveryForm.from(this)
}
