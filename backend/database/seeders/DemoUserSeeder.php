<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoUserSeeder extends Seeder
{
    /**
     * One account per platform role, signed in with DEMO_PASSWORD.
     *
     * @var list<array{name: string, email: string, role: PlatformRole}>
     */
    private const array USERS = [
        ['name' => 'Super Admin', 'email' => 'superadmin@seafood.test', 'role' => PlatformRole::SuperAdmin],
        ['name' => 'Admin', 'email' => 'admin@seafood.test', 'role' => PlatformRole::Admin],
    ];

    /**
     * Create the demo admin accounts. Skipped in production; safe to run more
     * than once, and a rerun resets the password to the current DEMO_PASSWORD.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoUserSeeder skipped: demo accounts are never created in production.');

            return;
        }

        foreach (self::USERS as $demo) {
            $user = self::demoUser($demo['name'], $demo['email']);
            $user->syncRoles([$demo['role']]);
        }
    }

    /**
     * A verified demo user with the demo password, created or updated by email.
     * Shared with DemoStoreSeeder for the store owners.
     */
    public static function demoUser(string $name, string $email, ?string $phone = null): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'phone' => $phone, 'password' => config()->string('demo.password')],
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
