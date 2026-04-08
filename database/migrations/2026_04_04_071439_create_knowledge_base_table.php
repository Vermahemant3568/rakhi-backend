<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches');
            $table->string('title');
            $table->longText('content');
            $table->string('pinecone_vector_id')->nullable();
            $table->string('pinecone_namespace', 100)->nullable();
            $table->string('source_file')->nullable();
            $table->string('file_type', 50)->nullable();
            $table->tinyInteger('is_synced')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
