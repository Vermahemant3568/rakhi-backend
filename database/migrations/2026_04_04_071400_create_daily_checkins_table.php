<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->enum('mood', ['great', 'good', 'okay', 'low', 'bad']);
            $table->integer('energy_level')->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable();
            $table->decimal('water_intake', 4, 2)->nullable();
            $table->tinyInteger('exercise_done')->default(0);
            $table->text('notes')->nullable();
            $table->date('checkin_date');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
    }
};
