<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Roles and categories are master data.
     * The demo stores and their products are seeded in production too, so the
     * released marketplace is never empty; only the demo admin accounts skip
     * themselves there. Every seeder is safe to run more than once.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            DemoUserSeeder::class,
            DemoStoreSeeder::class,
            DemoProductSeeder::class,
        ]);
    }
}
