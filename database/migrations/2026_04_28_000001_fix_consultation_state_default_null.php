<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Remove the DB-level default of 'pending' so consultation_state
            // is only set when completeOnboarding() is explicitly called.
            // Previously every new user got 'pending' immediately on row creation,
            // which caused the chat screen to open before onboarding was done.
            $table->enum('consultation_state', [
                'pending',
                'in_consultation',
                'generating_plans',
                'active_coaching',
            ])->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('consultation_state', [
                'pending',
                'in_consultation',
                'generating_plans',
                'active_coaching',
            ])->default('pending')->change();
        });
    }
};
