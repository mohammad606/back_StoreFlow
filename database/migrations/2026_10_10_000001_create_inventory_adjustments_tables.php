<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_adjustments')) {
            Schema::create('inventory_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('date');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'date']);
            });
        }

        if (! Schema::hasTable('inventory_adjustment_items')) {
            Schema::create('inventory_adjustment_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_adjustment_id')
                    ->constrained('inventory_adjustments')
                    ->cascadeOnDelete();
                $table->foreignId('store_id')->constrained('store');
                $table->integer('quantity_change');
                $table->timestamps();

                $table->unique(
                    ['inventory_adjustment_id', 'store_id'],
                    'inv_adj_items_adj_store_unique'
                );
            });
        }

        if (! Schema::hasIndex(
            'inventory_adjustment_items',
            ['inventory_adjustment_id', 'store_id'],
            'unique'
        )) {
            Schema::table('inventory_adjustment_items', function (Blueprint $table) {
                $table->unique(
                    ['inventory_adjustment_id', 'store_id'],
                    'inv_adj_items_adj_store_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_items');
        Schema::dropIfExists('inventory_adjustments');
    }
};
