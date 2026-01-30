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
            // Drop existing columns
            $table->dropColumn(['name', 'email', 'email_verified_at', 'password', 'remember_token']);
            
            // Add new columns
            $table->string('mobile')->unique();
            $table->string('country_code')->default('+91');
            $table->timestamp('mobile_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn(['mobile', 'country_code', 'mobile_verified_at', 'is_active']);
            
            // Restore original columns
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
        });
    }
};