<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('max_radius_km', 6, 2)->nullable();
            $table->string('fee_type');
            // Rupiah as whole numbers; which ones are set depends on type and fee_type.
            $table->unsignedInteger('flat_fee')->nullable();
            $table->unsignedInteger('fee_per_km')->nullable();
            $table->unsignedInteger('min_fee')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['store_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_shipping_methods');
    }
};
