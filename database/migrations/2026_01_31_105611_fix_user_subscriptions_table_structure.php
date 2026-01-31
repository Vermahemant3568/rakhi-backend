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
        Schema::table('user_subscriptions', function (Blueprint $table) {
            // Add missing columns
            $table->date('current_period_start')->nullable()->after('trial_end');
            $table->string('payment_provider')->nullable()->after('current_period_end');
            
            // Rename existing column
            $table->renameColumn('razorpay_subscription_id', 'provider_subscription_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['current_period_start', 'payment_provider']);
            $table->renameColumn('provider_subscription_id', 'razorpay_subscription_id');
        });
    }
};