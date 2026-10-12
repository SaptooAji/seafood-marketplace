<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\RecordStockMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\StockMovementIndexRequest;
use App\Http\Requests\Api\V1\Seller\StockMovementRequest;
use App\Http\Resources\Api\V1\StockMovementResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The stock book of the signed-in seller's store: stock in, offline sales,
 * shrinkage, and adjustments recorded from the app.
 */
class StockMovementController extends Controller
{
    /**
     * List the store's movements, newest first, 30 per page. Movements of
     * deleted variants and products stay in the history.
     */
    public function index(StockMovementIndexRequest $request): AnonymousResourceCollection
    {
        $store = $this->currentStore($request);

        Gate::authorize('viewAny', [StockMovement::class, $store]);

        $productIds = Product::withTrashed()->select('id')->whereBelongsTo($store);
        $variantIds = ProductVariant::withTrashed()->select('id')->whereIn('product_id', $productIds);

        $movements = StockMovement::query()
            ->whereIn('variant_id', $variantIds)
            ->with(['variant.product' => fn ($query) => $query->withTrashed(), 'createdBy'])
            ->when($request->validated('variant_id'), fn (Builder $query, int|string $id) => $query->where('variant_id', $id))
            ->when($request->validated('type'), fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($request->validated('from'), fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->validated('to'), fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->orderByDesc('id')
            ->paginate(30);

        return StockMovementResource::collection($movements);
    }

    /**
     * Record a movement for one of the store's variants, with the signed-in
     * user as the recorder.
     */
    public function store(StockMovementRequest $request, RecordStockMovement $recordStockMovement): JsonResponse
    {
        $store = $this->currentStore($request);

        Gate::authorize('create', [StockMovement::class, $store]);

        $variant = ProductVariant::query()
            ->whereKey($request->integer('variant_id'))
            ->whereHas('product', fn (Builder $query) => $query->whereBelongsTo($store))
            ->first()
            ?? throw ValidationException::withMessages(['variant_id' => 'Varian tidak ditemukan di toko ini.']);

        /** @var User $user */
        $user = $request->user();

        $movement = $recordStockMovement->handle(
            $variant,
            $request->type(),
            $request->qty(),
            by: $user,
            supplierName: $request->supplierName(),
            unitCost: $request->unitCost(),
            note: $request->note(),
        );

        $movement->load(['variant.product' => fn ($query) => $query->withTrashed(), 'createdBy']);

        return (new StockMovementResource($movement))->response()->setStatusCode(Response::HTTP_CREATED);
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
