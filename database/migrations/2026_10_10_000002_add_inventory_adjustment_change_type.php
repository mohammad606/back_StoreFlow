<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_quantity_history', function (Blueprint $table) {
            $table->enum('change_type', [
                'initial',
                'manual_update',
                'input',
                'order',
                'inventory_adjustment',
            ])->change();
        });
    }

    public function down(): void
    {
        DB::table('store_quantity_history')
            ->where('change_type', 'inventory_adjustment')
            ->update(['change_type' => 'manual_update']);

        Schema::table('store_quantity_history', function (Blueprint $table) {
            $table->enum('change_type', [
                'initial',
                'manual_update',
                'input',
                'order',
            ])->change();
        });
    }
};
