<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory_policies', function (Blueprint $table) {
            // Rename 'store' to 'store_memory' if it exists
            if (Schema::hasColumn('memory_policies', 'store')) {
                $table->renameColumn('store', 'store_memory');
            } else {
                $table->boolean('store_memory')->default(true);
            }
            
            // Add new columns if they don't exist
            if (!Schema::hasColumn('memory_policies', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            
            if (!Schema::hasColumn('memory_policies', 'retention_days')) {
                $table->integer('retention_days')->default(365);
            }
            
            if (!Schema::hasColumn('memory_policies', 'description')) {
                $table->text('description')->nullable();
            }
            
            // Make type unique if not already
            if (!Schema::hasIndex('memory_policies', ['type'])) {
                $table->unique('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('memory_policies', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'retention_days', 'description']);
            $table->dropUnique(['type']);
            
            if (Schema::hasColumn('memory_policies', 'store_memory')) {
                $table->renameColumn('store_memory', 'store');
            }
        });
    }
};