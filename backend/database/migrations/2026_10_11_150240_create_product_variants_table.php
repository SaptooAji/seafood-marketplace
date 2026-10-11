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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('unit');
            // Rupiah per unit.
            $table->unsignedInteger('price');
            // Only changed through RecordStockMovement.
            $table->decimal('stock', 10, 3)->default(0);
            $table->timestamp('stock_confirmed_at')->nullable();
            $table->decimal('min_qty', 10, 3)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
