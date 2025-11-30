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
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->date('date');
            $t->string('noa');
            $t->string('sender');
            $t->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $t->string('customer_name');
            $t->timestamps();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->index('date');
            $t->index('noa');
            $t->index('customer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
