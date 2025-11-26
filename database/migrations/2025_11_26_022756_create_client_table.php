<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('name',100);
            $t->string('phone')->nullable();
            $t->string('address')->nullable();
            $t->timestamps();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->index('user_id');
            $t->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
