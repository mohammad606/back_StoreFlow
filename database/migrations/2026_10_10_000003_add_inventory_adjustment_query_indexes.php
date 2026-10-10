<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustment_items', function (Blueprint $table) {
            $table->index(
                ['store_id', 'inventory_adjustment_id'],
                'inventory_adjustment_items_store_adjustment_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustment_items', function (Blueprint $table) {
            $table->dropIndex('inventory_adjustment_items_store_adjustment_idx');
        });
    }
};
