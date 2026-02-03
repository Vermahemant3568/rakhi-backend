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
        Schema::create('daily_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('mood', ['low','neutral','good','great'])->nullable();
            $table->enum('energy', ['low','medium','high'])->nullable();
            $table->boolean('diet_followed')->nullable();
            $table->boolean('activity_done')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id','date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_checkins');
    }
};
