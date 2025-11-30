<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        Schema::create("order_items", function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $t->foreignId('product_id')->constrained('store');
            $t->string('name', 100);
            $t->integer('quantity')->default(0);
            $t->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists("order_items");
    }
};
