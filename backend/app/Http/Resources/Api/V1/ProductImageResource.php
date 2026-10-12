<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product photo with the public URLs of the photo and its thumbnail.
 *
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'sort_order' => $this->sort_order,
        ];
    }
}
