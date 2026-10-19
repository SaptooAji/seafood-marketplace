<?php

declare(strict_types=1);

use App\Data\StoreData;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Build store input as sellers send it, shared by the store Action tests.
 *
 * @param  array<string, string|null>  $overrides  Keyed by StoreData constructor parameter.
 */
function storeData(array $overrides = []): StoreData
{
    return new StoreData(...array_merge([
        'name' => 'Kepiting Pak Budi',
        'description' => 'Kepiting bakau segar langsung dari tambak.',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
        'city' => 'Jakarta Utara',
        'lat' => '-6.2087634',
        'lng' => '106.8455990',
        'bankName' => 'BCA',
        'bankAccountNo' => '1234567890',
        'bankAccountName' => 'Budi Santoso',
        'stockReminderTime' => '06:00',
    ], $overrides));
}

/**
 * A black-and-white PNG of the given size whose file stays small however
 * large it claims to be, like a decompression bomb: one bit per pixel, all
 * zero, compressed. Decoding it fully would take width x height x 4 bytes.
 */
function blankPng(int $width, int $height, string $name = 'besar.png'): UploadedFile
{
    $row = "\0".str_repeat("\0", intdiv($width + 7, 8));
    $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    $png = "\x89PNG\r\n\x1a\n"
        .$chunk('IHDR', pack('NNCCCCC', $width, $height, 1, 0, 0, 0, 0))
        .$chunk('IDAT', (string) gzcompress(str_repeat($row, $height), 9))
        .$chunk('IEND', '');

    return UploadedFile::fake()->createWithContent($name, $png);
}

/**
 * A JPEG stored as $width x $height with an EXIF Orientation tag, as phones
 * save a photo taken sideways (6 = rotate 90° clockwise to view).
 */
function rotatedJpeg(int $width, int $height, int $orientation = 6): UploadedFile
{
    $jpeg = UploadedFile::fake()->image('miring.jpg', $width, $height)->getContent();
    // APP1 Exif segment: a big-endian TIFF header and one IFD entry, 0x0112 (Orientation), SHORT.
    $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00"."\x00\x00\x00\x00";
    $exif = "\xFF\xE1".pack('n', 2 + 6 + strlen($tiff))."Exif\x00\x00".$tiff;

    return UploadedFile::fake()->createWithContent('miring.jpg', substr($jpeg, 0, 2).$exif.substr($jpeg, 2));
}

/**
 * Run without a current tenant. While a tenant is set, Filament assigns every
 * new product to it, so records of other stores are created through this.
 *
 * @template T
 *
 * @param  Closure(): T  $create
 * @return T
 */
function outsideTenant(Closure $create): mixed
{
    $tenant = Filament::getTenant();
    Filament::setTenant(null, isQuiet: true);

    try {
        return $create();
    } finally {
        Filament::setTenant($tenant, isQuiet: true);
    }
}
