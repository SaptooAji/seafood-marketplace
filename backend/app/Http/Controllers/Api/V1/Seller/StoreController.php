<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\CreateStore;
use App\Actions\UpdateStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\StoreRequest;
use App\Http\Resources\Api\V1\StoreResource;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The signed-in seller's own store. In the MVP a user belongs to one store,
 * so the store is found from the user instead of the URL.
 */
class StoreController extends Controller
{
    /**
     * Open a store for the seller. A seller who already owns one gets a 409
     * from UserAlreadyOwnsStore, so StorePolicy::create is not checked here.
     */
    public function store(StoreRequest $request, CreateStore $createStore): JsonResponse
    {
        $store = $createStore->handle($this->user($request), $request->toData());

        return (new StoreResource($store))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Show the store the seller belongs to, as owner or staff.
     */
    public function show(Request $request): StoreResource
    {
        return new StoreResource($this->currentStore($request));
    }

    /**
     * Update the store's details; only the owner may do this.
     */
    public function update(StoreRequest $request, UpdateStore $updateStore): StoreResource
    {
        $store = $this->currentStore($request);

        Gate::authorize('update', $store);

        return new StoreResource($updateStore->handle($store, $request->toData()));
    }

    /**
     * The store the seller belongs to, or a 404 when they have none. A staff
     * member may also open their own store, so the owned store comes first
     * (`owner` sorts before `staff`).
     */
    private function currentStore(Request $request): Store
    {
        return $this->user($request)->stores()
            ->orderByPivot('role')
            ->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
