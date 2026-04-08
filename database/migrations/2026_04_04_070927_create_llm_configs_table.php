<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->text('api_key');
            $table->string('model_name', 100);
            $table->tinyInteger('is_active')->default(0);
            $table->integer('max_tokens')->default(1000);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->decimal('top_p', 3, 2)->default(0.95);
            $table->json('extra_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_configs');
    }
};
