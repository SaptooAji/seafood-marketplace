<?php

declare(strict_types=1);

namespace App\Support;

use App\Data\ProcessedPhoto;
use App\Exceptions\InvalidProductPhoto;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Turns an uploaded photo into a WebP photo and a square thumbnail, with the
 * standard, sizes, and quality from `config/catalog.php`. The photo keeps its
 * ratio and is only scaled down, never up; the thumbnail is cut from its
 * middle. EXIF data (camera, location) is stripped.
 */
class ProductPhotoProcessor
{
    private const string NOT_AN_IMAGE = 'File harus berupa gambar (JPG, PNG, atau WebP).';

    /**
     * @throws InvalidProductPhoto when the file is not a readable image or is below the photo standard
     */
    public function process(UploadedFile $file): ProcessedPhoto
    {
        $this->ensureStandard($file->getRealPath());

        $manager = new ImageManager(Driver::class, strip: true);

        try {
            // Turned upright from its EXIF orientation while decoding.
            $image = $manager->decodePath($file->getRealPath());
        } catch (DecoderException $e) {
            throw new InvalidProductPhoto('photo', self::NOT_AN_IMAGE, $e);
        }

        // Changed in place, photo first and thumbnail from it, so the
        // full-size bitmap is never copied.
        $maxSide = config()->integer('catalog.photo_max_side');
        $photo = $this->encode($image->scaleDown($maxSide, $maxSide));

        $thumbnailSide = config()->integer('catalog.thumbnail_side');
        $thumbnail = $this->encode($image->cover($thumbnailSide, $thumbnailSide));

        return new ProcessedPhoto($photo, $thumbnail);
    }

    /**
     * Check the photo against the standard from its header alone, before any
     * decoding (VAL-4). An EXIF rotation only swaps width and height, which
     * changes neither the shorter side nor the ratio.
     *
     * @throws InvalidProductPhoto
     */
    private function ensureStandard(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new InvalidProductPhoto('photo', self::NOT_AN_IMAGE);
        }

        [$width, $height] = $size;
        $maxPixels = config()->integer('catalog.photo_max_pixels');

        if ($width * $height > $maxPixels) {
            $megapixels = rtrim(rtrim(number_format($maxPixels / 1_000_000, 1, ',', ''), '0'), ',');

            throw new InvalidProductPhoto('photo', "Foto terlalu besar, maksimal {$megapixels} megapiksel.");
        }

        $minSide = config()->integer('catalog.photo_min_side');

        if (min($width, $height) < $minSide) {
            throw new InvalidProductPhoto('photo', "Foto terlalu kecil. Sisi terpendek foto minimal {$minSide} piksel.");
        }

        if (max($width, $height) / min($width, $height) > config()->float('catalog.photo_max_ratio') + 0.001) {
            throw new InvalidProductPhoto('photo', 'Foto terlalu memanjang. Potong foto ke rasio 1:1 sampai 4:3 lebih dulu.');
        }
    }

    private function encode(ImageInterface $image): string
    {
        return (string) $image->encode(new WebpEncoder(config()->integer('catalog.photo_quality'), strip: true));
    }
}
