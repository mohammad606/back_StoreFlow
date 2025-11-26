<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('all_input_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('all_input_id')->constrained('all_inputs')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('store');
            $table->string('name',100);
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('all_input_items');
    }
};

