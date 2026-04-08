<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rakhi_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type', 100);
            $table->string('title', 200);
            $table->text('rule_content');
            $table->json('applies_to_coaches')->nullable();
            $table->tinyInteger('is_active')->default(1);
            $table->integer('priority')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rakhi_rules');
    }
};
