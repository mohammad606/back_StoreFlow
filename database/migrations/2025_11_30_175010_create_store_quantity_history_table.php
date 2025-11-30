<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_quantity_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('store')->onDelete('cascade');
            $table->integer('quantity');
            $table->integer('change_amount');
            $table->enum('change_type', ['initial', 'manual_update', 'input', 'order']);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('store_id');
            $table->index('created_at');
            $table->index(['store_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_quantity_history');
    }
};
