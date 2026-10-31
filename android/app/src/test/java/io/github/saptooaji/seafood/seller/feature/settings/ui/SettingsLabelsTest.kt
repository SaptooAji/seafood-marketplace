package io.github.saptooaji.seafood.seller.feature.settings.ui

import io.github.saptooaji.seafood.seller.feature.store.domain.BankAccount
import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertFalse
import org.junit.jupiter.api.Assertions.assertNull
import org.junit.jupiter.api.Test
import org.junit.jupiter.params.ParameterizedTest
import org.junit.jupiter.params.provider.CsvSource
import java.time.LocalTime

/** The pure parts of Pengaturan: the reminder field, and what the rows say. */
class SettingsLabelsTest {
    @ParameterizedTest(name = "{0}:{1} is {2}")
    @CsvSource(
        "06, 30, 06:30",
        "6, 5, 06:05",
        "00, 00, 00:00",
        "23, 59, 23:59",
        "' 7 ', ' 15 ', 07:15",
    )
    fun `a time typed in the reminder sheet is read`(
        hour: String,
        minute: String,
        expected: String,
    ) {
        assertEquals(LocalTime.parse(expected), ReminderTime.parse(hour, minute))
    }

    @ParameterizedTest(name = "\"{0}\":\"{1}\" is refused")
    @CsvSource(
        "24, 00",
        "12, 60",
        "-1, 00",
        "'', 30",
        "06, ''",
        "ab, 00",
        "06, 3x",
        "123, 00",
        "6.5, 00",
    )
    fun `a time that is not on a 24-hour clock is refused`(
        hour: String,
        minute: String,
    ) {
        assertNull(ReminderTime.parse(hour, minute))
    }

    @ParameterizedTest(name = "{0} reads {1}")
    @CsvSource(
        "06:00, 06.00",
        "06:30, 06.30",
        "18:05, 18.05",
    )
    fun `a reminder time is shown with a dot, as Indonesian times are`(
        time: String,
        shown: String,
    ) {
        assertEquals(shown, ReminderTime.format(LocalTime.parse(time)))
    }

    @Test
    fun `the bank row shows the bank, the last four digits, and the holder`() {
        val summary = SettingsLabels.bankSummary(BankAccount("BCA", "7700112233", "Budi Santoso"))

        assertEquals("BCA •••2233, Budi Santoso", summary)
        assertFalse(summary.contains("770011"))
    }

    @Test
    fun `a short account number still hides all but four digits`() {
        assertEquals("BRI •••2345, Siti", SettingsLabels.bankSummary(BankAccount("BRI", "12345", "Siti")))
    }

    @ParameterizedTest(name = "\"{0}\" shows {1}")
    @CsvSource(
        "Budi Santoso, B",
        "budi, B",
        "'  rina amelia', R",
        "Ṡiti, Ṡ",
    )
    fun `a user without an avatar shows the first letter of the name`(
        name: String,
        initial: String,
    ) {
        assertEquals(initial, SettingsLabels.initial(name))
    }
}
