import com.android.build.api.variant.HostTestBuilder
import java.util.Properties
import java.util.zip.ZipFile

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.compose)
    alias(libs.plugins.kotlin.serialization)
    alias(libs.plugins.ksp)
    alias(libs.plugins.hilt)
    alias(libs.plugins.android.junit5)
    alias(libs.plugins.ktlint)
    alias(libs.plugins.detekt)
}

// Machine-specific settings live in android/local.properties, never in Git.
val localProperties =
    Properties().apply {
        val file = rootProject.file("local.properties")
        if (file.isFile) file.inputStream().use(::load)
    }

// The emulator reaches the host machine's localhost:8000 at 10.0.2.2. A phone
// on USB uses `adb reverse tcp:8000 tcp:8000` and http://localhost:8000/api/v1/.
val debugApiBaseUrl = localProperties.getProperty("seafood.apiBaseUrl") ?: "http://10.0.2.2:8000/api/v1/"

// The production API is set in Tahap 7; a release build never talks plain HTTP (MOB-3).
val releaseApiBaseUrl = localProperties.getProperty("seafood.releaseApiBaseUrl") ?: "https://seafood.invalid/api/v1/"
// The Google web client ID that ID tokens are requested for (MOB-2: public, but
// kept out of Git so a fork uses its own Google Cloud project). Empty hides the
// "Masuk dengan Google" button, as in CI.
val googleServerClientId = localProperties.getProperty("seafood.googleServerClientId")?.trim().orEmpty()
require(googleServerClientId.isEmpty() || googleServerClientId.endsWith(".apps.googleusercontent.com")) {
    "seafood.googleServerClientId must be a Google OAuth client ID ending in .apps.googleusercontent.com"
}

require(releaseApiBaseUrl.trim().startsWith("https://")) {
    "seafood.releaseApiBaseUrl must use https:// (MOB-3), got $releaseApiBaseUrl"
}

android {
    namespace = "io.github.saptooaji.seafood.seller"
    compileSdk = 37

    defaultConfig {
        applicationId = "io.github.saptooaji.seafood.seller"
        minSdk = 26
        targetSdk = 37
        versionCode = 1
        versionName = "0.4.0"
        buildConfigField("String", "GOOGLE_SERVER_CLIENT_ID", "\"$googleServerClientId\"")
    }

    buildTypes {
        debug {
            buildConfigField("String", "API_BASE_URL", "\"$debugApiBaseUrl\"")
        }
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            buildConfigField("String", "API_BASE_URL", "\"$releaseApiBaseUrl\"")
            // Temporary: signed with the debug key until the release keystore in Tahap 7.
            signingConfig = signingConfigs.getByName("debug")
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    buildFeatures {
        compose = true
        buildConfig = true
    }

    testOptions {
        unitTests.isIncludeAndroidResources = true
    }

    lint {
        abortOnError = true
        checkDependencies = true
    }
}

kotlin {
    jvmToolchain(17)
}

// AGP creates unit tests for debug only. The release unit tests check the
// release manifest, where debug-only tooling is gone (MOB-8, src/testRelease).
androidComponents {
    beforeVariants(selector().withBuildType("release")) { variant ->
        variant.hostTests[HostTestBuilder.UNIT_TEST_TYPE]?.enable = true
    }
}

ktlint {
    android.set(true)
}

detekt {
    buildUponDefaultConfig = true
    config.setFrom(rootProject.file("config/detekt/detekt.yml"))
    source.setFrom("src/main/java", "src/test/java", "src/testDebug/java", "src/testRelease/java")
}

// detekt 1.23 runs on the Kotlin compiler it was built with, not the project's.
configurations.matching { it.name == "detekt" }.configureEach {
    resolutionStrategy.eachDependency {
        if (requested.group == "org.jetbrains.kotlin") {
            useVersion(
                libs.versions.detekt.kotlin
                    .get(),
            )
        }
    }
}

// `check` runs what CI runs: ktlint, detekt, Android Lint, and every unit test.
tasks.named("check") {
    dependsOn("detekt", "lintDebug")
}

// docs/security-standards.md §11 on the built release APK (#262): R8 has run (MOB-5), and no
// third-party secret is inside (MOB-2), neither a known key pattern nor a secret value from
// backend/.env when that file exists (on a developer machine, not in CI). Only the names of
// what was found are printed, never the values.
val verifyReleaseApk by tasks.registering {
    group = "verification"
    description = "Checks that R8 shrank the release APK (MOB-5) and that it holds no third-party secrets (MOB-2)."
    dependsOn("assembleRelease")
    val apkDir =
        layout.buildDirectory
            .dir("outputs/apk/release")
            .get()
            .asFile
    val mapping =
        layout.buildDirectory
            .file("outputs/mapping/release/mapping.txt")
            .get()
            .asFile
    val envFile = rootProject.file("../backend/.env")
    doLast {
        check(mapping.isFile && mapping.length() > 0) { "MOB-5: no R8 mapping at $mapping; is the release build minified?" }
        val apk = apkDir.listFiles { file -> file.extension == "apk" }?.singleOrNull() ?: error("no single release APK in $apkDir")
        val patterns =
            mapOf(
                "a Google API key" to Regex("AIza[0-9A-Za-z_\\-]{35}"),
                "an sk- secret key" to Regex("sk-[A-Za-z0-9_\\-]{20,}"),
                "a private key" to Regex("-----BEGIN [A-Z ]*PRIVATE KEY-----"),
                "a GitHub token" to Regex("gh[pousr]_[A-Za-z0-9]{36}"),
                "an AWS access key" to Regex("AKIA[0-9A-Z]{16}"),
            )
        val secretName = Regex("KEY|SECRET|PASSWORD|TOKEN")
        val envSecrets =
            if (envFile.isFile) {
                envFile.readLines().mapNotNull { line ->
                    val parts = line.split("=", limit = 2)
                    val key = parts[0].trim()
                    val value =
                        parts
                            .getOrNull(1)
                            ?.trim()
                            ?.trim('"', '\'')
                            .orEmpty()
                    // Short values such as a local DB password would match by chance.
                    (key to value).takeIf { !key.startsWith("#") && secretName.containsMatchIn(key) && value.length >= 12 }
                }
            } else {
                emptyList()
            }
        val found = mutableListOf<String>()
        ZipFile(apk).use { zip ->
            zip.entries().asSequence().filterNot { it.isDirectory }.forEach { entry ->
                // Byte for byte, so dex strings and binary resources are searched as they are.
                val content = zip.getInputStream(entry).use { it.readBytes() }.toString(Charsets.ISO_8859_1)
                patterns.forEach { (name, regex) -> if (regex.containsMatchIn(content)) found += "$name in ${entry.name}" }
                envSecrets.forEach { (key, value) -> if (content.contains(value)) found += "backend/.env $key in ${entry.name}" }
            }
        }
        check(found.isEmpty()) { "MOB-2: the release APK holds secrets:\n" + found.joinToString("\n") }
        logger.lifecycle(
            "MOB-2, MOB-5: ${apk.name} holds no known secrets (${envSecrets.size} values from backend/.env checked); R8 wrote ${mapping.name}.",
        )
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.activity.compose)
    implementation(libs.androidx.navigation.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(platform(libs.compose.bom))
    implementation(libs.compose.ui)
    implementation(libs.compose.ui.graphics)
    implementation(libs.compose.ui.tooling.preview)
    implementation(libs.compose.material3)
    implementation(libs.hilt.android)
    ksp(libs.hilt.compiler)
    implementation(libs.androidx.hilt.lifecycle.viewmodel.compose)
    implementation(libs.kotlinx.coroutines.android)
    implementation(libs.kotlinx.serialization.core)
    implementation(libs.kotlinx.serialization.json)
    implementation(libs.retrofit)
    implementation(libs.retrofit.kotlinx.serialization)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging)
    implementation(libs.datastore)
    implementation(libs.tink.android)
    implementation(libs.androidx.credentials)
    implementation(libs.androidx.credentials.play.services)
    implementation(libs.googleid)
    implementation(libs.maplibre.android)
    implementation(libs.coil.compose)
    implementation(libs.coil.network.okhttp)

    debugImplementation(libs.compose.ui.tooling)
    debugImplementation(libs.compose.ui.test.manifest)

    testImplementation(platform(libs.junit.bom))
    testImplementation(libs.junit.jupiter)
    testRuntimeOnly(libs.junit.platform.launcher)
    // Robolectric and the Compose test rules are JUnit 4; the vintage engine
    // runs them on the JUnit Platform next to the JUnit 5 tests.
    testImplementation(libs.junit4)
    testRuntimeOnly(libs.junit.vintage.engine)
    testImplementation(libs.robolectric)
    testImplementation(libs.okhttp.mockwebserver)
    testImplementation(libs.kotlinx.coroutines.test)
    testImplementation(libs.turbine)
    testImplementation(libs.mockk)
    testImplementation(libs.androidx.test.core)
    testImplementation(platform(libs.compose.bom))
    testImplementation(libs.compose.ui.test.junit4)
    // The add-product flow test runs the app's Hilt graph with a fake server (#242).
    testImplementation(libs.hilt.android.testing)
    kspTest(libs.hilt.compiler)
}
