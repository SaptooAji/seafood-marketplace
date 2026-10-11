<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Account\Enums\PlatformRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Create every platform role. Safe to run more than once.
     */
    public function run(): void
    {
        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
