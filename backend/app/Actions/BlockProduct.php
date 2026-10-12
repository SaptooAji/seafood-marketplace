<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\InvalidProductData;
use App\Models\Product;

/**
 * An admin takes a product out of the catalog for breaking the rules.
 */
class BlockProduct
{
    public const int MAX_REASON_LENGTH = 500;

    /**
     * Block the product with a reason the seller sees. The seller's own
     * active switch is left alone, so unblocking restores what they chose.
     * Blocking a blocked product again only replaces the reason; it keeps
     * when the product was first blocked.
     *
     * @throws InvalidProductData on `reason` when it is empty or too long
     */
    public function handle(Product $product, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidProductData('reason', 'Alasan blokir wajib diisi.');
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            throw new InvalidProductData('reason', 'Alasan blokir maksimal '.self::MAX_REASON_LENGTH.' karakter.');
        }

        $product->forceFill([
            'blocked_at' => $product->blocked_at ?? now(),
            'block_reason' => $reason,
        ])->save();
    }
}
