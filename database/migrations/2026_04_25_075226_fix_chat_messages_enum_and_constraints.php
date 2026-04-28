<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add voice_summary to message_type enum
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','voice','pdf','image','call_action','voice_summary') DEFAULT 'text'");

        // Add foreign key on user_id (was missing — security gap)
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','voice','pdf','image','call_action') DEFAULT 'text'");
    }
};
