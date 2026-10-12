<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\DeleteVariant;
use App\Actions\SaveVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ProductVariantRequest;
use App\Http\Resources\Api\V1\ProductVariantResource;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Variants of the signed-in seller's products. Their stock is not changed
 * here: it only changes through the stock book. A variant or product of
 * another store is a 404, so its existence is not revealed.
 */
class ProductVariantController extends Controller
{
    /**
     * Add a variant to the product, with its opening stock.
     */
    public function store(ProductVariantRequest $request, int $product, SaveVariant $saveVariant): JsonResponse
    {
        $product = $this->currentStore($request)->products()->findOrFail($product);

        Gate::authorize('update', $product);

        /** @var User $user */
        $user = $request->user();
        $variant = $saveVariant->handle($product, $request->toData(), by: $user);

        // Refreshed for the stock and its confirmation, set by the database and the stock book.
        return (new ProductVariantResource($variant->refresh()))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update the variant's details. Its stock stays as it is.
     */
    public function update(ProductVariantRequest $request, int $variant, SaveVariant $saveVariant): ProductVariantResource
    {
        $variant = $this->findVariant($request, $variant);

        Gate::authorize('update', $variant->product);

        return new ProductVariantResource($saveVariant->handle($variant->product, $request->toData(), $variant));
    }

    /**
     * Soft delete the variant.
     */
    public function destroy(Request $request, int $variant, DeleteVariant $deleteVariant): Response
    {
        $variant = $this->findVariant($request, $variant);

        Gate::authorize('update', $variant->product);

        $deleteVariant->handle($variant);

        return response()->noContent();
    }

    /**
     * A variant of one of the current store's products, or a 404. Variants of
     * a deleted product are left out with it.
     */
    private function findVariant(Request $request, int $id): ProductVariant
    {
        $store = $this->currentStore($request);

        return ProductVariant::query()
            ->with('product')
            ->whereHas('product', fn (Builder $product) => $product->whereBelongsTo($store))
            ->findOrFail($id);
    }

    /**
     * The store the seller belongs to, or a 404 when they have none.
     */
    private function currentStore(Request $request): Store
    {
        /** @var User $user */
        $user = $request->user();

        return $user->currentStore() ?? abort(Response::HTTP_NOT_FOUND);
    }
}
