<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches');
            $table->foreignId('language_id')->constrained('languages');
            $table->string('template_type', 100);
            $table->string('title', 200);
            $table->longText('content');
            $table->json('variables')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompt_templates');
    }
};
