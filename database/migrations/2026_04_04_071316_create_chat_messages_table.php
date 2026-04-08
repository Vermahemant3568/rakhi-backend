<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('chat_sessions');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['user', 'rakhi']);
            $table->longText('message');
            $table->enum('message_type', ['text', 'voice', 'pdf', 'image'])->default('text');
            $table->string('file_url', 500)->nullable();
            $table->integer('tokens_used')->nullable();
            $table->string('llm_provider', 50)->nullable();
            $table->unsignedBigInteger('coach_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
