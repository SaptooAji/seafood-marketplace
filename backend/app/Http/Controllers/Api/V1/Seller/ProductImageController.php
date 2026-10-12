<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\AddProductImage;
use App\Actions\RemoveProductImage;
use App\Actions\ReorderProductImages;
use App\Exceptions\InvalidProductData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ProductImageRequest;
use App\Http\Requests\Api\V1\Seller\ReorderProductImagesRequest;
use App\Http\Resources\Api\V1\ProductImageResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Photos of a product of the signed-in seller's store. A product or photo of
 * another store is a 404, so its existence is not revealed.
 */
class ProductImageController extends Controller
{
    /**
     * Add a photo after the product's other photos.
     */
    public function store(ProductImageRequest $request, int $product, AddProductImage $addProductImage): JsonResponse
    {
        $product = $this->findProduct($request, $product);

        Gate::authorize('update', $product);

        /** @var UploadedFile $photo */
        $photo = $request->file('photo');
        $image = $addProductImage->handle($product, $photo);

        return (new ProductImageResource($image))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Delete a photo of the product and its files.
     */
    public function destroy(Request $request, int $product, int $image, RemoveProductImage $removeProductImage): Response
    {
        $product = $this->findProduct($request, $product);
        $image = $product->images()->findOrFail($image);

        Gate::authorize('update', $product);

        $removeProductImage->handle($image);

        return response()->noContent();
    }

    /**
     * Set the order of the product's photos and return them in it.
     */
    public function reorder(ReorderProductImagesRequest $request, int $product, ReorderProductImages $reorderProductImages): AnonymousResourceCollection
    {
        $product = $this->findProduct($request, $product);

        Gate::authorize('update', $product);

        try {
            $reorderProductImages->handle($product, $request->imageIds());
        } catch (InvalidProductData $e) {
            // The action names the list `images`; the request field is `image_ids`.
            throw ValidationException::withMessages(['image_ids' => $e->getMessage()]);
        }

        return ProductImageResource::collection($product->images()->get());
    }

    /**
     * One of the current store's products, or a 404, also when the user has
     * no store.
     */
    private function findProduct(Request $request, int $id): Product
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->currentStore() ?? abort(Response::HTTP_NOT_FOUND);

        return $store->products()->findOrFail($id);
    }
}
