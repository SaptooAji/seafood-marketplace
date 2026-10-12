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

it('turns a large photo into a WebP photo and thumbnail', function () {
    $photo = processPhoto(UploadedFile::fake()->image('tuna.jpg', 3000, 2000));

    expect(imageInfo($photo->photo))->toBe([1600, 1067, 'image/webp'])
        ->and(imageInfo($photo->thumbnail))->toBe([400, 267, 'image/webp']);
});

it('does not enlarge a small photo', function () {
    $photo = processPhoto(UploadedFile::fake()->image('udang.png', 800, 600));

    expect(imageInfo($photo->photo))->toBe([800, 600, 'image/webp'])
        ->and(imageInfo($photo->thumbnail))->toBe([400, 300, 'image/webp']);
});

it('fits the longest side of a portrait photo', function () {
    $photo = processPhoto(UploadedFile::fake()->image('kepiting.jpg', 2000, 3000));

    expect(imageInfo($photo->photo))->toBe([1067, 1600, 'image/webp'])
        ->and(imageInfo($photo->thumbnail))->toBe([267, 400, 'image/webp']);
});

it('drops EXIF data such as the camera location', function () {
    $jpeg = UploadedFile::fake()->image('cumi.jpg', 640, 480)->getContent();
    // Minimal APP1 Exif segment (empty big-endian TIFF directory) right after the SOI marker.
    $exif = "\xFF\xE1\x00\x16Exif\x00\x00MM\x00\x2A\x00\x00\x00\x08\x00\x00\x00\x00\x00\x00";
    $file = UploadedFile::fake()->createWithContent('cumi.jpg', substr($jpeg, 0, 2).$exif.substr($jpeg, 2));

    expect($file->getContent())->toContain('Exif');

    $photo = processPhoto($file);

    expect($photo->photo)->not->toContain('Exif')
        ->and($photo->thumbnail)->not->toContain('Exif');
});

it('rejects a file that is not an image', function () {
    $file = UploadedFile::fake()->createWithContent('katalog.pdf', '%PDF-1.4 not an image');

    expect(fn () => processPhoto($file))
        ->toThrow(fn (InvalidProductPhoto $e) => expect($e->field)->toBe('photo'));
});

it('reads its sizes and quality from the catalog config', function () {
    config(['catalog.photo_max_side' => 1000, 'catalog.thumbnail_side' => 200]);

    $photo = processPhoto(UploadedFile::fake()->image('tuna.jpg', 3000, 2000));

    expect(imageInfo($photo->photo))->toBe([1000, 667, 'image/webp'])
        ->and(imageInfo($photo->thumbnail))->toBe([200, 133, 'image/webp']);
});
