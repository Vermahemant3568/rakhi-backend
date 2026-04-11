<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proactive_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reminder_type', 50);   // 'medication', 'meal', 'sleep', 'activity', 'followup'
            $table->string('habit_key', 100);       // e.g. 'insulin_before_dinner'
            $table->timestamp('sent_at')->useCurrent();
            $table->boolean('user_responded')->default(false);
            $table->timestamp('responded_at')->nullable();

            $table->index(['user_id', 'reminder_type', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proactive_reminders');
    }
};
