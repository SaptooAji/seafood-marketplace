package io.github.saptooaji.seafood.seller.security

import org.junit.jupiter.api.Assertions.assertEquals
import org.junit.jupiter.api.Assertions.assertTrue
import org.junit.jupiter.api.Test
import org.w3c.dom.Document
import org.w3c.dom.Element
import java.io.File
import javax.xml.parsers.DocumentBuilderFactory

/**
 * The security settings in the app's XML sources (docs/security-standards.md
 * §11): cleartext only in debug and only for the emulator host (MOB-3), the
 * token store kept out of backups (MOB-7), and only the launcher activity
 * exported (MOB-8). Unit tests run with the module directory as the working
 * directory, so the paths are relative to `android/app`.
 */
class PlatformSecurityConfigTest {
    @Test
    fun `release allows no cleartext traffic`() {
        val config = xml("src/main/res/xml/network_security_config.xml")

        assertEquals("false", config.single("base-config").getAttribute("cleartextTrafficPermitted"))
        assertTrue(
            config.all("*").none { it.getAttribute("cleartextTrafficPermitted") == "true" },
            "no element in the release config may permit cleartext",
        )
    }

    @Test
    fun `debug allows cleartext only for the emulator host and localhost`() {
        val config = xml("src/debug/res/xml/network_security_config.xml")

        assertEquals("false", config.single("base-config").getAttribute("cleartextTrafficPermitted"))
        val cleartext = config.all("domain-config").filter { it.getAttribute("cleartextTrafficPermitted") == "true" }
        val domains = cleartext.flatMap { it.all("domain") }.map { it.textContent.trim() }.toSet()
        assertEquals(setOf("10.0.2.2", "localhost"), domains)
    }

    @Test
    fun `the manifest uses the network security config and never turns cleartext on`() {
        val application = xml("src/main/AndroidManifest.xml").single("application")

        assertEquals("@xml/network_security_config", application.android("networkSecurityConfig"))
        assertTrue(application.android("usesCleartextTraffic") != "true")
    }

    @Test
    fun `backups and device transfers leave out the token store`() {
        val application = xml("src/main/AndroidManifest.xml").single("application")
        assertEquals("@xml/data_extraction_rules", application.android("dataExtractionRules"))
        assertEquals("@xml/backup_rules", application.android("fullBackupContent"))

        val rules = xml("src/main/res/xml/data_extraction_rules.xml")
        for (section in listOf("cloud-backup", "device-transfer")) {
            assertTrue(rules.single(section).excludesDataStore(), "$section must exclude the DataStore files")
        }
        assertTrue(xml("src/main/res/xml/backup_rules.xml").documentElement.excludesDataStore())
    }

    @Test
    fun `only the main activity is exported`() {
        val application = xml("src/main/AndroidManifest.xml").single("application")
        val exported =
            listOf("activity", "activity-alias", "service", "receiver", "provider")
                .flatMap { application.all(it) }
                .filter { it.android("exported") == "true" }
                .map { it.android("name") }

        assertEquals(listOf(".MainActivity"), exported)
    }

    private fun Element.excludesDataStore(): Boolean = all("exclude").any { it.getAttribute("domain") == "file" && it.getAttribute("path") == "datastore/" }

    private fun xml(path: String): Document {
        val file = File(path)
        assertTrue(file.isFile, "missing $path")
        return DocumentBuilderFactory.newInstance().apply { isNamespaceAware = true }.newDocumentBuilder().parse(file)
    }

    private fun Document.single(tag: String): Element = documentElement.takeIf { it.tagName == tag } ?: documentElement.all(tag).single()

    private fun Document.all(tag: String): List<Element> = documentElement.all(tag)

    private fun Element.all(tag: String): List<Element> {
        val nodes = getElementsByTagName(tag)
        return (0 until nodes.length).map { nodes.item(it) as Element }
    }

    private fun Element.android(name: String): String = getAttributeNS(ANDROID_NS, name)

    private companion object {
        const val ANDROID_NS = "http://schemas.android.com/apk/res/android"
    }
}
