<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Data\Recipe;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public const int MAX_QUERY_LENGTH = 100;

    /**
     * The recipe list: every recipe in its group, or the recipes matching
     * `?q=` by name or ingredient. The static recipes are searched in
     * memory, so there is no query to the database.
     */
    public function index(Request $request): View
    {
        $query = $request->query('q');
        $term = is_string($query) ? mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH) : '';

        $recipes = $term === '' ? Recipe::all() : Recipe::search($term);

        $groups = [];

        foreach (Recipe::GROUPS as $id => $group) {
            $inGroup = array_values(array_filter($recipes, fn (Recipe $recipe): bool => $recipe->group === $id));

            if ($inGroup !== []) {
                $groups[$id] = [...$group, 'recipes' => $inGroup];
            }
        }

        return view('recipes.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => count($recipes),
        ]);
    }
}
