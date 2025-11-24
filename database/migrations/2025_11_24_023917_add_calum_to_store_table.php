<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('store', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id');
            $t->string('name');
            $t->integer('quantity')->default(0);
            $t->integer('box')->default(1);
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $t->index('user_id');
            $t->index('name');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('store');
    }
};
