<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('consultation_state', [
                'pending',           // Just completed onboarding
                'in_consultation',   // Actively gathering health data
                'generating_plans',  // Plans being generated
                'active_coaching'    // Normal coaching mode
            ])->default('pending')->after('first_consultation_complete');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('consultation_state');
        });
    }
};
