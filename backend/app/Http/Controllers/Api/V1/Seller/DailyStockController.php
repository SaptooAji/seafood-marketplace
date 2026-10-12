<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\ConfirmDailyStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ConfirmDailyStockRequest;
use App\Http\Resources\Api\V1\DailyStockResource;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The "Stok hari ini" screen of the seller app: every active variant of the
 * store's active products, to count and confirm each morning.
 */
class DailyStockController extends Controller
{
    /**
     * List the variants to count, grouped by product, products by name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $store = $this->currentStore($request);

        Gate::authorize('viewAny', [StockMovement::class, $store]);

        return $this->screen($store);
    }

    /**
     * Apply the counts and confirm, then return the screen as it is now.
     */
    public function confirm(ConfirmDailyStockRequest $request, ConfirmDailyStock $confirmDailyStock): AnonymousResourceCollection
    {
        $store = $this->currentStore($request);

        Gate::authorize('create', [StockMovement::class, $store]);

        /** @var User $user */
        $user = $request->user();
        $confirmDailyStock->handle($store, $request->counts(), $request->boolean('confirm_all'), $user);

        return $this->screen($store);
    }

    /**
     * The store's active products that have active variants, with only those
     * variants loaded.
     */
    private function screen(Store $store): AnonymousResourceCollection
    {
        $activeVariants = fn (Builder|HasMany $variant) => $variant->where('is_active', true);

        $products = $store->products()
            ->where('is_active', true)
            ->whereHas('variants', $activeVariants)
            ->with(['variants' => $activeVariants])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return DailyStockResource::collection($products);
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
