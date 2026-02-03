<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->string('action');
            $table->string('resource')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address');
            $table->string('user_agent');
            $table->timestamps();
            
            $table->index(['admin_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};