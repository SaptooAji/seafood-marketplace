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
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            // Branches point to their main store; always null in the MVP.
            $table->foreignId('parent_store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('phone', 20);
            $table->text('address');
            $table->string('city');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('logo')->nullable();
            $table->string('bank_name');
            // Text because the encrypted cast stores a long ciphertext.
            $table->text('bank_account_no');
            $table->string('bank_account_name');
            $table->time('stock_reminder_time')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
