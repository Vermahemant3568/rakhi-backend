<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('otp_logs', 'attempt_count')) {
                $table->unsignedTinyInteger('attempt_count')->default(0)->after('is_used');
            }
        });
    }

    public function down(): void
    {
        Schema::table('otp_logs', function (Blueprint $table) {
            $table->dropColumn('attempt_count');
        });
    }
};
