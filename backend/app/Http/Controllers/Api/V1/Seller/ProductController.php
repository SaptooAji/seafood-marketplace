<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\CreateProduct;
use App\Actions\DeleteProduct;
use App\Actions\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ProductIndexRequest;
use App\Http\Requests\Api\V1\Seller\ProductRequest;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Products of the signed-in seller's store. A product of another store is a
 * 404, so its existence is not revealed.
 */
class ProductController extends Controller
{
    /**
     * List the store's products, newest first, 20 per page.
     */
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $store = $this->currentStore($request);

        Gate::authorize('viewAny', [Product::class, $store]);

        $status = $request->validated('status');

        // A blocked product only counts as blocked, whatever its own switch says.
        $products = $store->products()
            ->with(['category', 'variants', 'images'])
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true)->whereNull('blocked_at'))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false)->whereNull('blocked_at'))
            ->when($status === 'blocked', fn (Builder $query) => $query->whereNotNull('blocked_at'))
            ->when($request->validated('search'), fn (Builder $query, string $search) => $query->whereLike('name', '%'.$search.'%'))
            ->latest()
            ->orderByDesc('id')
            ->paginate(20);

        return ProductResource::collection($products);
    }

    /**
     * Create a product with its first variants.
     */
    public function store(ProductRequest $request, CreateProduct $createProduct): JsonResponse
    {
        $store = $this->currentStore($request);

        Gate::authorize('create', [Product::class, $store]);

        /** @var User $user */
        $user = $request->user();
        $product = $createProduct->handle($store, $request->toData(), $request->variants(), $user);

        return $this->detail($product)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show one product with its category's attributes.
     */
    public function show(Request $request, int $product): ProductResource
    {
        $product = $this->findProduct($request, $product);

        Gate::authorize('view', $product);

        return $this->detail($product);
    }

    /**
     * Update the product's details. The slug and the variants stay as they are.
     */
    public function update(ProductRequest $request, int $product, SaveProduct $saveProduct): ProductResource
    {
        $store = $this->currentStore($request);
        $product = $store->products()->findOrFail($product);

        Gate::authorize('update', $product);

        return $this->detail($saveProduct->handle($store, $request->toData(), $product));
    }

    /**
     * Soft delete the product.
     */
    public function destroy(Request $request, int $product, DeleteProduct $deleteProduct): Response
    {
        $product = $this->findProduct($request, $product);

        Gate::authorize('delete', $product);

        $deleteProduct->handle($product);

        return response()->noContent();
    }

    /**
     * The product with everything the detail response shows.
     */
    private function detail(Product $product): ProductResource
    {
        $product->load(['category', 'variants', 'images', 'attributeValues']);

        return (new ProductResource($product))->withEffectiveAttributes($product->category->effectiveAttributes());
    }

    /**
     * One of the current store's products, or a 404.
     */
    private function findProduct(Request $request, int $id): Product
    {
        return $this->currentStore($request)->products()->findOrFail($id);
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
