<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('role');
            $table->string('device_token')->nullable()->after('device_id');
            $table->enum('status', ['free_trial', 'active', 'blocked'])->default('free_trial')->after('device_token');
            $table->timestamp('trial_ends_at')->nullable()->after('status');
            $table->timestamp('subscription_starts_at')->nullable()->after('trial_ends_at');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_starts_at');

            $table->index('device_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'device_id',
                'device_token',
                'status',
                'trial_ends_at',
                'subscription_starts_at',
                'subscription_ends_at',
            ]);
        });
    }
};
