<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('image_url', 500);
            $table->string('meal_name')->nullable();
            $table->enum('meal_time', ['breakfast', 'lunch', 'dinner', 'snack'])->nullable();
            $table->decimal('calories', 8, 2)->nullable();
            $table->decimal('protein', 8, 2)->nullable();
            $table->decimal('carbs', 8, 2)->nullable();
            $table->decimal('fat', 8, 2)->nullable();
            $table->decimal('fiber', 8, 2)->nullable();
            $table->json('analysis_raw')->nullable();
            $table->text('rakhi_advice')->nullable();
            $table->date('logged_date');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('session_id')->references('id')->on('chat_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_logs');
    }
};
