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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('type', ['trial', 'subscription']);
            $table->integer('amount'); // in rupees
            $table->string('currency')->default('INR');

            $table->string('payment_provider'); // razorpay
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_subscription_id')->nullable();

            $table->enum('status', ['success', 'failed', 'pending']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};