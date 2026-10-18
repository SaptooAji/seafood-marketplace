<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations: give every store without a pickup row an active,
     * free pickup method, the default way to receive an order (#212). A
     * store that already has a pickup row, on or off, keeps it as it is.
     * Safe to run again: stores that got one are skipped. Written with the
     * query builder so it does not depend on the models.
     */
    public function up(): void
    {
        $now = now();

        DB::table('store_shipping_methods')->insertUsing(
            ['store_id', 'type', 'max_radius_km', 'fee_type', 'flat_fee', 'fee_per_km', 'min_fee', 'is_active', 'created_at', 'updated_at'],
            DB::table('stores')
                ->select('stores.id')
                ->selectRaw("'pickup', null, 'flat', 0, null, null, true, ?, ?", [$now, $now])
                ->whereNotExists(fn (Builder $query) => $query
                    ->select(DB::raw(1))
                    ->from('store_shipping_methods')
                    ->whereColumn('store_shipping_methods.store_id', 'stores.id')
                    ->where('store_shipping_methods.type', 'pickup')),
        );
    }

    /**
     * Reverse the migrations: nothing. A pickup row may have been changed by
     * its seller since, so it is never removed.
     */
    public function down(): void
    {
        //
    }
};
