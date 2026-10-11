<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\SaveShippingMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ShippingMethodRequest;
use App\Http\Resources\Api\V1\ShippingMethodResource;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Shipping methods of the signed-in seller's store. Methods are deactivated,
 * never deleted.
 */
class ShippingMethodController extends Controller
{
    /**
     * List the store's methods to any member.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $store = $this->currentStore($request);

        Gate::authorize('viewAny', [StoreShippingMethod::class, $store]);

        return ShippingMethodResource::collection($store->shippingMethods()->orderBy('id')->get());
    }

    /**
     * Save the method for its type: 201 when the type is new, 200 when the
     * store's existing method of that type is replaced.
     */
    public function store(ShippingMethodRequest $request, SaveShippingMethod $saveShippingMethod): JsonResponse
    {
        $store = $this->currentStore($request);

        Gate::authorize('create', [StoreShippingMethod::class, $store]);

        $method = $saveShippingMethod->handle($store, $request->toData());

        return (new ShippingMethodResource($method))
            ->response()
            ->setStatusCode($method->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Update one of the store's methods; its type never changes. A method of
     * another store is a 404, so its existence is not revealed.
     */
    public function update(ShippingMethodRequest $request, int $shippingMethod, SaveShippingMethod $saveShippingMethod): ShippingMethodResource
    {
        $method = $this->currentStore($request)->shippingMethods()->findOrFail($shippingMethod);

        Gate::authorize('update', $method);

        return new ShippingMethodResource($saveShippingMethod->handle($method->store, $request->toData($method->type)));
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
