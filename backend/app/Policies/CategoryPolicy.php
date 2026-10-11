<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Categories are managed by platform admins only, through Shield permissions
 * granted by RoleSeeder. Reading categories in the public API (#46) needs no policy.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Category');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('View:Category');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Category');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('Update:Category');
    }

    /**
     * Whether children block the delete is checked by the delete action, which
     * tells the admin why instead of hiding the button.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can('Delete:Category');
    }

    /**
     * Rows are reordered by dragging in the table.
     */
    public function reorder(User $user): bool
    {
        return $user->can('Update:Category');
    }
}
