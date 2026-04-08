<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('plan_type', ['diet', 'fitness', 'consultation']);
            $table->foreignId('coach_id')->constrained('coaches');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->string('file_url', 500);
            $table->json('plan_data')->nullable();
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plans');
    }
};
