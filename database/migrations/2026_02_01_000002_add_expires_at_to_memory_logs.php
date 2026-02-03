<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('memory_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('memory_logs', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('pinecone_id');
                $table->index('expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('memory_logs', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });
    }
};