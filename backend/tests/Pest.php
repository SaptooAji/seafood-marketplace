<?php

declare(strict_types=1);

use App\Data\StoreData;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Build store input as sellers send it, shared by the store Action tests.
 *
 * @param  array<string, string|null>  $overrides  Keyed by StoreData constructor parameter.
 */
function storeData(array $overrides = []): StoreData
{
    return new StoreData(...array_merge([
        'name' => 'Kepiting Pak Budi',
        'description' => 'Kepiting bakau segar langsung dari tambak.',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
        'city' => 'Jakarta Utara',
        'lat' => '-6.2087634',
        'lng' => '106.8455990',
        'bankName' => 'BCA',
        'bankAccountNo' => '1234567890',
        'bankAccountName' => 'Budi Santoso',
        'stockReminderTime' => '06:00',
    ], $overrides));
}

/**
 * Run without a current tenant. While a tenant is set, Filament assigns every
 * new product to it, so records of other stores are created through this.
 *
 * @template T
 *
 * @param  Closure(): T  $create
 * @return T
 */
function outsideTenant(Closure $create): mixed
{
    $tenant = Filament::getTenant();
    Filament::setTenant(null, isQuiet: true);

    try {
        return $create();
    } finally {
        Filament::setTenant($tenant, isQuiet: true);
    }
}
