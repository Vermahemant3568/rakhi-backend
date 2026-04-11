<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 100);        // e.g. 'diet_habit', 'health_condition'
            $table->text('value');             // e.g. 'eats outside daily, mostly rice and dal'
            $table->string('source', 20)->default('chat'); // 'chat' | 'consultation' | 'checkin'
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['user_id', 'key']); // one value per key per user
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_memories');
    }
};
