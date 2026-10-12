<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlatformRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Shield permissions every platform role gets, named like
     * `shield:generate` names them (`<Ability>:<Model>`). Kept here so a fresh
     * database, CI, and production get them without running Shield by hand.
     *
     * @var list<string>
     */
    private const array ADMIN_PERMISSIONS = [
        'ViewAny:Store',
        'View:Store',
        'ViewAny:Category',
        'View:Category',
        'Create:Category',
        'Update:Category',
        'Delete:Category',
        'ViewAny:Product',
        'View:Product',
        'Update:Product',
    ];

    /**
     * Create every platform role and grant it the admin panel permissions.
     * Safe to run more than once.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ADMIN_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // DatabaseSeeder mutes model events, which normally clear this cache
        // when a permission is created; without this, givePermissionTo() below
        // cannot find the new permissions.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->givePermissionTo(self::ADMIN_PERMISSIONS);
        }
    }
}
