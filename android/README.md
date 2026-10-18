# Seafood Marketplace: aplikasi penjual

Aplikasi Android untuk penjual (Kotlin, Jetpack Compose). Aturan kode ada di [docs/engineering-standards.md](../docs/engineering-standards.md) bagian Android, dan aturan keamanan di [docs/security-standards.md](../docs/security-standards.md) §11 (`MOB-*`).

## Yang dibutuhkan

- **Android Studio.** Plugin Kotlin sudah ikut, dan compiler Kotlin diunduh Gradle sesuai `gradle/libs.versions.toml`, jadi Kotlin tidak perlu di-install terpisah.
- **JDK 17** untuk Gradle. JDK bawaan Android Studio (JDK 21) juga bisa, karena kode tetap dikompilasi ke Java 17.
- **Android SDK** dengan platform API 37. Gradle mengunduhnya otomatis kalau lisensi SDK sudah diterima di Android Studio.
- **Emulator dengan image "Google Play"**, bukan "Google APIs". Login Google lewat Credential Manager butuh Google Play Services.

## Menjalankan

1. Jalankan backend di `http://localhost:8000` (lihat README backend).
2. Buka folder `android/` di Android Studio, tunggu Gradle sync, lalu jalankan konfigurasi `app` di emulator.

Build debug menghubungi `http://10.0.2.2:8000/api/v1/`. Bagi emulator, `10.0.2.2` berarti `localhost` di komputer Anda. HTTP biasa hanya diizinkan di build debug, dan hanya ke `10.0.2.2` dan `localhost` (MOB-3).

### Di HP fisik

Sambungkan HP lewat USB dengan USB debugging menyala, lalu jalankan:

```
adb reverse tcp:8000 tcp:8000
```

Isi `android/local.properties` (file ini tidak masuk Git):

```
seafood.apiBaseUrl=http://localhost:8000/api/v1/
```

Setelah itu `localhost:8000` di HP diteruskan ke komputer. HP tidak perlu satu Wi-Fi dengan komputer, dan firewall tidak perlu dibuka. `adb reverse` perlu dijalankan ulang setiap kali HP dicabut.

### Pengaturan di `local.properties`

| Kunci | Fungsi | Bawaan |
| --- | --- | --- |
| `seafood.apiBaseUrl` | Alamat API build debug. Boleh alamat root (`http://localhost:8000`) atau lengkap; `/api/v1/` ditambahkan otomatis | `http://10.0.2.2:8000/api/v1/` |
| `seafood.releaseApiBaseUrl` | Alamat API build rilis, wajib `https://`. Build gagal kalau memakai `http://` | `https://seafood.invalid/api/v1/` (diisi di Tahap 7) |
| `seafood.googleServerClientId` | Client ID **web** Google (sama dengan `GOOGLE_CLIENT_ID` di `backend/.env`). Aplikasi meminta ID token untuk client ini, dan server memeriksanya di `GOOGLE_ALLOWED_CLIENT_IDS`. Publik, bukan rahasia (MOB-2) | kosong: tombol "Masuk dengan Google" tidak tampil |

### Login Google

Selain `seafood.googleServerClientId`, project Google Cloud yang sama butuh **OAuth client Android** dengan package `io.github.saptooaji.seafood.seller` dan SHA-1 kunci penanda tangan. Untuk debug, SHA-1 diambil dari `~/.android/debug.keystore`:

```
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android | grep SHA1
```

Client Android hanya mendaftarkan aplikasi; ID token tetap ditujukan ke client web. Kunci rilis (Tahap 7) punya SHA-1 lain yang perlu didaftarkan juga.

## Pemeriksaan

Jalankan dari folder `android/`:

| Perintah | Fungsi |
| --- | --- |
| `./gradlew check` | ktlint, detekt, Android Lint, dan semua unit test (debug dan rilis). Sama dengan CI, dan dijalankan hook pre-push untuk perubahan di `android/` |
| `./gradlew ktlintFormat` | Merapikan format Kotlin |
| `./gradlew testDebugUnitTest` | Unit test debug saja, termasuk UI test Compose |
| `./gradlew assembleRelease` | Build rilis dengan R8 (MOB-5). Sementara ditandatangani dengan kunci debug sampai keystore rilis dibuat di Tahap 7 |

Di Git Bash Windows, jalankan `sh ./gradlew ...` kalau `./gradlew` tidak bisa dieksekusi.

### Test

- **Unit test** (JUnit 5): `app/src/test`.
- **UI test Compose** berjalan di JVM dengan Robolectric, tanpa emulator: `app/src/testDebug`. Test ini ada di source set debug karena activity test Compose (`ui-test-manifest`) hanya ikut build debug. API test-nya sama dengan instrumented test, jadi bisa dipindah ke `androidTest` untuk dijalankan di emulator tanpa ditulis ulang.
- **Test manifest rilis**: `app/src/testRelease`. Misalnya, hanya activity peluncur yang bisa dibuka aplikasi lain (MOB-8). Komponen tooling khusus debug tidak ikut diperiksa.
- Robolectric menjalankan test di API 34 (`app/src/test/resources/robolectric.properties`). API 35 ke atas di Robolectric butuh Java 21, sedangkan proyek ini memakai JDK 17.

## Memanggil API

Setiap fitur membuat service Retrofit dari `Retrofit` yang disediakan Hilt (`NetworkModule`), lalu membungkus panggilannya dengan `apiCall(json) { service.something() }`. Hasilnya `ApiResult`, bukan exception: `Success`, `Invalid` (galat 422 per field), `Forbidden`, `NotFound`, `Conflict`, `TooManyRequests`, `ServerError`, atau `NetworkError`.

- Token dipasang otomatis oleh `AuthInterceptor`, hanya ke host API dan tidak ke `auth/login`, `auth/register`, atau `auth/google`.
- Jawaban 401 dari endpoint lain berarti token kedaluwarsa atau dicabut. Token dihapus, `SessionEvents` memancarkan `SignedOut`, dan aplikasi kembali ke layar pertama dengan back stack bersih. Layar tidak perlu menangani 401 sendiri.
- Log HTTP hanya ada di build debug (tag `Http` di Logcat), dengan token, password, ID token Google, dan nomor rekening tersamar.

## Struktur

```
app/src/main/java/io/github/saptooaji/seafood/seller/
  core/
    designsystem/theme/   # tema dari design/android/theme
    network/              # ApiBaseUrl, klien HTTP (Retrofit), ApiResult, AuthInterceptor
    session/              # TokenStore terenkripsi (DataStore + Tink), SessionEvents
    util/                 # dispatcher coroutine (Hilt)
  feature/<nama>/{data,domain,ui}/
  navigation/             # NavHost dan route type-safe
```

Tema, warna, font, dan ikon berasal dari `design/android/`. Kalau token desain berubah, salin ulang dari sana, jangan diubah langsung di sini.
