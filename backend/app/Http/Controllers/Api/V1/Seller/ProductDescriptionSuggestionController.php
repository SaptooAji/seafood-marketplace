<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Seller;

use App\Actions\SuggestProductDescription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Seller\ProductDescriptionSuggestionRequest;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * An AI suggestion for the description and SEO summary of the product the
 * seller is writing. Nothing is saved: the app fills its form, and the seller
 * reviews it before saving the product.
 */
class ProductDescriptionSuggestionController extends Controller
{
    public function __invoke(ProductDescriptionSuggestionRequest $request, SuggestProductDescription $suggestProductDescription): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store = $user->currentStore() ?? abort(Response::HTTP_NOT_FOUND);

        Gate::authorize('suggestDescription', [Product::class, $store]);

        $suggestion = $suggestProductDescription->handle($store, $user, $request->toData());

        return response()->json(['data' => [
            'description' => $suggestion->description,
            'seo_description' => $suggestion->seoDescription,
        ]]);
    }
}
