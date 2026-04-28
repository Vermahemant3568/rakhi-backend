<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->string('detected_script', 20)->nullable()->after('detected_language');
            // Values: 'roman' | 'devanagari' | 'tamil' | 'telugu' | 'marathi' | 'latin'
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropColumn('detected_script');
        });
    }
};
