<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tracks the plan generation pipeline state independently of consultation_state
            // Values: null | collecting_data | ready_to_generate | generating | completed | failed
            $table->string('plan_generation_state', 30)->nullable()->after('consultation_state');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan_generation_state');
        });
    }
};
