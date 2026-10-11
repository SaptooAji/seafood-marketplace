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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            // Restrict: the stock book is history and must not disappear with a variant.
            $table->foreignId('variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->string('type');
            // Signed: positive adds stock, negative removes it.
            $table->decimal('qty', 10, 3);
            $table->decimal('stock_after', 10, 3);
            // Only for stock coming in.
            $table->string('supplier_name')->nullable();
            $table->unsignedInteger('unit_cost')->nullable();
            // The foreign key to orders is added in Tahap 5.
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['variant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
