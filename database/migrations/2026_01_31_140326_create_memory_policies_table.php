<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('memory_policies', function (Blueprint $table) {
            $table->id();

            $table->string('type')->unique(); 
            // emotional_state, goals, preferences, health_data, conversations, achievements, concerns, relationships, habits, feedback

            $table->boolean('store_memory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(5);
            $table->integer('retention_days')->default(365); // How long to keep memories
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('memory_policies');
    }
};
