<?php

declare(strict_types=1);

use App\Data\ProcessedPhoto;
use App\Exceptions\InvalidProductPhoto;
use App\Support\ProductPhotoProcessor;
use Illuminate\Http\UploadedFile;

/**
 * Width, height, and media type of encoded image bytes.
 *
 * @return array{int, int, string}
 */
function imageInfo(string $binary): array
{
    $info = getimagesizefromstring($binary);

    expect($info)->not->toBeFalse();

    return [$info[0], $info[1], $info['mime']];
}

function processPhoto(UploadedFile $file): ProcessedPhoto
{
    return app(ProductPhotoProcessor::class)->process($file);
}

/**
 * The red, green, and blue values of one pixel of encoded image bytes.
 *
 * @return array{int, int, int}
 */
function pixelAt(string $binary, int $x, int $y): array
{
    $image = imagecreatefromstring($binary);
    $color = imagecolorat($image, $x, $y);

    return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
}

/**
 * A portrait JPEG in three bands: red at the top, green in the middle, blue at the bottom.
 */
function bandedPortrait(int $width, int $height): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    $band = intdiv($height, 3);
    imagefilledrectangle($image, 0, 0, $width, $band, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 0, $band, $width, 2 * $band, imagecolorallocate($image, 0, 255, 0));
    imagefilledrectangle($image, 0, 2 * $band, $width, $height, imagecolorallocate($image, 0, 0, 255));

    ob_start();
    imagejpeg($image, null, 95);

    return UploadedFile::fake()->createWithContent('pita.jpg', (string) ob_get_clean());
}

describe('accepted photos', function () {
    it('accepts photos from the shortest side and widest ratio allowed', function (int $width, int $height) {
        $photo = processPhoto(UploadedFile::fake()->image('tuna.jpg', $width, $height));

        expect(imageInfo($photo->photo))->toBe([$width, $height, 'image/webp']);
    })->with([
        '800 x 600, landscape 4:3' => [800, 600],
        '600 x 800, portrait 3:4' => [600, 800],
        '600 x 600, square' => [600, 600],
    ]);

    it('scales a large photo down to 1600 and keeps its ratio, with a square thumbnail', function () {
        $photo = processPhoto(UploadedFile::fake()->image('tuna.jpg', 2400, 1800));

        expect(imageInfo($photo->photo))->toBe([1600, 1200, 'image/webp'])
            ->and(imageInfo($photo->thumbnail))->toBe([400, 400, 'image/webp']);
    });

    it('fits the longest side of a portrait photo', function () {
        $photo = processPhoto(UploadedFile::fake()->image('kepiting.jpg', 1800, 2400));

        expect(imageInfo($photo->photo))->toBe([1200, 1600, 'image/webp'])
            ->and(imageInfo($photo->thumbnail))->toBe([400, 400, 'image/webp']);
    });

    it('does not enlarge a small photo, but its thumbnail is still square', function () {
        $photo = processPhoto(UploadedFile::fake()->image('udang.png', 800, 600));

        expect(imageInfo($photo->photo))->toBe([800, 600, 'image/webp'])
            ->and(imageInfo($photo->thumbnail))->toBe([400, 400, 'image/webp']);
    });

    it('cuts the thumbnail from the middle of the photo', function () {
        $photo = processPhoto(bandedPortrait(600, 800));

        [$red, $green, $blue] = pixelAt($photo->thumbnail, 200, 200);

        expect(imageInfo($photo->thumbnail))->toBe([400, 400, 'image/webp'])
            ->and($green)->toBeGreaterThan(200)
            ->and($red)->toBeLessThan(60)
            ->and($blue)->toBeLessThan(60);
    });

    it('turns a photo taken sideways upright before using it', function () {
        // Stored 800 x 600 with Orientation 6: it is a 600 x 800 portrait.
        $photo = processPhoto(rotatedJpeg(800, 600));

        expect(imageInfo($photo->photo))->toBe([600, 800, 'image/webp']);
    });
});

describe('refused photos', function () {
    it('refuses a photo below the standard on the photo field', function (Closure $file, string $message) {
        expect(fn () => processPhoto($file()))
            ->toThrow(function (InvalidProductPhoto $e) use ($message) {
                expect($e->field)->toBe('photo')
                    ->and($e->getMessage())->toContain($message);
            });
    })->with('photos below the standard');

    it('rejects a file that is not an image', function () {
        $file = UploadedFile::fake()->createWithContent('katalog.pdf', '%PDF-1.4 not an image');

        expect(fn () => processPhoto($file))
            ->toThrow(fn (InvalidProductPhoto $e) => expect($e->field)->toBe('photo'));
    });
});

describe('security', function () {
    it('refuses a decompression bomb without decoding it', function () {
        // 20000 x 20000 would need about 1.6 GB of memory once decoded.
        $bomb = blankPng(20000, 20000);
        memory_reset_peak_usage();
        $before = memory_get_usage();

        expect(fn () => processPhoto($bomb))
            ->toThrow(fn (InvalidProductPhoto $e) => expect($e->getMessage())->toContain('megapiksel'));

        expect(memory_get_peak_usage() - $before)->toBeLessThan(16 * 1024 * 1024);
    });

    it('drops EXIF data such as the camera location', function () {
        $jpeg = UploadedFile::fake()->image('cumi.jpg', 800, 600)->getContent();
        // Minimal APP1 Exif segment (empty big-endian TIFF directory) right after the SOI marker.
        $exif = "\xFF\xE1\x00\x16Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00";
        $file = UploadedFile::fake()->createWithContent('cumi.jpg', substr($jpeg, 0, 2).$exif.substr($jpeg, 2));

        expect($file->getContent())->toContain('Exif');

        $photo = processPhoto($file);

        expect($photo->photo)->not->toContain('Exif')
            ->and($photo->thumbnail)->not->toContain('Exif');
    });
});

describe('config', function () {
    it('reads its sizes and quality from the catalog config', function () {
        config(['catalog.photo_max_side' => 1000, 'catalog.thumbnail_side' => 200]);

        $photo = processPhoto(UploadedFile::fake()->image('tuna.jpg', 2400, 1800));

        expect(imageInfo($photo->photo))->toBe([1000, 750, 'image/webp'])
            ->and(imageInfo($photo->thumbnail))->toBe([200, 200, 'image/webp']);
    });

    it('reads the shortest side from the catalog config', function () {
        config(['catalog.photo_min_side' => 1000]);

        expect(fn () => processPhoto(UploadedFile::fake()->image('tuna.jpg', 800, 600)))
            ->toThrow(fn (InvalidProductPhoto $e) => expect($e->getMessage())->toContain('1000 piksel'));
    });

    it('reads the widest ratio and the pixel limit from the catalog config', function () {
        config(['catalog.photo_max_ratio' => 2.0, 'catalog.photo_max_pixels' => 600_000]);

        // 1000 x 500 is 2:1 and 0.5 MP; 800 x 800 is square but 0.64 MP.
        expect(imageInfo(processPhoto(UploadedFile::fake()->image('lebar.jpg', 1000, 500))->photo))->toBe([1000, 500, 'image/webp'])
            ->and(fn () => processPhoto(UploadedFile::fake()->image('tuna.jpg', 800, 800)))->toThrow(InvalidProductPhoto::class);
    });
});
