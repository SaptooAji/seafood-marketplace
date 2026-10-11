<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

/**
 * Only super admins may read the activity log; nobody may change it.
 * Registered in AppServiceProvider, since the model lives in the activitylog
 * package and is not found by policy auto-discovery.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(PlatformRole::SuperAdmin);
    }

    public function view(User $user, Activity $activity): bool
    {
        return $user->hasRole(PlatformRole::SuperAdmin);
    }
}
