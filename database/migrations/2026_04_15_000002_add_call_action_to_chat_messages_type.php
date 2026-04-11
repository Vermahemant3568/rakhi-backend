<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Modify enum to add call_action type
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','voice','pdf','image','call_action') DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE chat_messages MODIFY COLUMN message_type ENUM('text','voice','pdf','image') DEFAULT 'text'");
    }
};
