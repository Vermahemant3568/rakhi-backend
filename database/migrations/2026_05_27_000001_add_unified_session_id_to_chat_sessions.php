<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds unified_session_id to chat_sessions.
 *
 * This is the canonical identifier that ties a voice session and its parent
 * chat session together as ONE conversation thread. Both sessions share the
 * same unified_session_id so any query can retrieve the full conversation
 * regardless of mode.
 *
 * Backfill logic:
 *  - Chat sessions without a parent → unified_session_id = their own id
 *  - Voice sessions with a parent   → unified_session_id = parent's id
 *  - Voice sessions without a parent → unified_session_id = their own id
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('unified_session_id')->nullable()->after('parent_chat_session_id');
            $table->index('unified_session_id', 'idx_chat_sessions_unified');
        });

        // Backfill: voice sessions inherit parent's id; others use own id
        \DB::statement("
            UPDATE chat_sessions
            SET unified_session_id = COALESCE(parent_chat_session_id, id)
        ");
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_chat_sessions_unified');
            $table->dropColumn('unified_session_id');
        });
    }
};
