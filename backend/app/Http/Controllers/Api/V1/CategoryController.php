<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ListCategoryTree;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The category tree with effective attributes, for the seller app's product form.
 */
class CategoryController extends Controller
{
    public function __invoke(ListCategoryTree $listCategoryTree): AnonymousResourceCollection
    {
        return CategoryResource::collection($listCategoryTree->handle());
    }
}
