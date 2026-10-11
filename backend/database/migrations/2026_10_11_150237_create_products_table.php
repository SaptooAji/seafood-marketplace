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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->index()->constrained()->cascadeOnDelete();
            // Restrict: a category that still has products cannot be deleted.
            $table->foreignId('category_id')->index()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('condition');
            $table->string('sales_mode');
            // Pre-order settings; null for ready stock products.
            $table->decimal('daily_quota', 10, 3)->nullable();
            $table->unsignedTinyInteger('lead_time_days')->nullable();
            $table->time('cutoff_time')->nullable();
            $table->unsignedSmallInteger('shelf_life_days')->nullable();
            $table->text('storage_instructions')->nullable();
            // Set by the seller.
            $table->boolean('is_active')->default(true);
            // Set by an admin; the seller cannot undo it.
            $table->timestamp('blocked_at')->nullable();
            $table->text('block_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
