<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('all_inputs', function (Blueprint $t) {
            $t->id();
            $t->date('date');
            $t->enum('type', ['production', 'return']);
            $t->string('noa');
            $t->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $t->string('customer_name');
            $t->timestamps();

            $t->index('date');
            $t->index('noa');
            $t->index('customer_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('all_inputs');
    }
};
