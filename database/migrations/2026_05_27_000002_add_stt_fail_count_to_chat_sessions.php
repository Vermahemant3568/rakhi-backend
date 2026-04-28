<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            // Tracks consecutive STT failures in a voice session.
            // When this reaches 3, Rakhi suggests switching to chat.
            $table->tinyInteger('stt_fail_count')->unsigned()->default(0)->after('call_failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('stt_fail_count');
        });
    }
};
