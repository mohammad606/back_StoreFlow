<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('representative_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('representative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->date('date');
            $table->string('noa', 50);
            $table->string('status', 20)->default('pending');
            $table->string('decision_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['representative_id', 'status']);
        });

        Schema::create('representative_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('representative_invoice_id')
                ->constrained('representative_invoices')
                ->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('store')->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(['representative_invoice_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('representative_invoice_items');
        Schema::dropIfExists('representative_invoices');
    }
};
