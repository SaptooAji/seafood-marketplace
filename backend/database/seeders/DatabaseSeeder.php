<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Roles and categories are master data;
     * the demo seeders skip themselves in production. Every seeder is safe to
     * run more than once.
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
