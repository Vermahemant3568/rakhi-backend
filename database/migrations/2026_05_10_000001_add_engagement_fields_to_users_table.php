<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('engagement_state')->default('active')->after('consultation_state');
            // active | slow_response | non_responsive
            $table->timestamp('last_message_at')->nullable()->after('engagement_state');
            $table->unsignedSmallInteger('escalation_call_count')->default(0)->after('last_message_at');
            $table->timestamp('last_escalation_at')->nullable()->after('escalation_call_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['engagement_state', 'last_message_at', 'escalation_call_count', 'last_escalation_at']);
        });
    }
};
