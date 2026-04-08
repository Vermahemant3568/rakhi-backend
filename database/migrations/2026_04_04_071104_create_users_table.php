<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('mobile', 15)->unique();
            $table->string('email', 150)->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->foreignId('language_id')->nullable()->constrained('languages');
            $table->string('profile_photo')->nullable();
            $table->enum('activity_level', ['sedentary', 'light', 'moderate', 'active', 'very_active'])->nullable();
            $table->enum('stress_level', ['low', 'medium', 'high'])->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->enum('diet_preference', ['veg', 'non_veg', 'vegan', 'eggetarian'])->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->tinyInteger('is_banned')->default(0);
            $table->text('ban_reason')->nullable();
            $table->integer('onboarding_step')->default(0);
            $table->tinyInteger('onboarding_complete')->default(0);
            $table->tinyInteger('notification_enabled')->default(0);
            $table->tinyInteger('microphone_enabled')->default(0);
            $table->string('fcm_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
