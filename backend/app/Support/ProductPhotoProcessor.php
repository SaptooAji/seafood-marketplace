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
 * Turns an uploaded photo into a WebP photo and thumbnail, with sizes and
 * quality from `config/catalog.php`. Photos are only scaled down, never up,
 * and EXIF data (camera, location) is stripped.
 */
class ProductPhotoProcessor
{
    /**
     * @throws InvalidProductPhoto when the file is not a readable image
     */
    public function process(UploadedFile $file): ProcessedPhoto
    {
        $manager = new ImageManager(Driver::class, strip: true);

        try {
            $image = $manager->decodePath($file->getRealPath());
        } catch (DecoderException $e) {
            throw new InvalidProductPhoto('photo', 'File harus berupa gambar (JPG, PNG, atau WebP).', $e);
        }

        // Scaled in place, photo first and thumbnail from it, so the full-size
        // bitmap is never copied.
        $photo = $this->scaleAndEncode($image, config()->integer('catalog.photo_max_side'));
        $thumbnail = $this->scaleAndEncode($image, config()->integer('catalog.thumbnail_side'));

        return new ProcessedPhoto($photo, $thumbnail);
    }

    /**
     * Fit the longest side within $maxSide and encode as WebP.
     */
    private function scaleAndEncode(ImageInterface $image, int $maxSide): string
    {
        return (string) $image
            ->scaleDown($maxSide, $maxSide)
            ->encode(new WebpEncoder(config()->integer('catalog.photo_quality'), strip: true));
    }
}
