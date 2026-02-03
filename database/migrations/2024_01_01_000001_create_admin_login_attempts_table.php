<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('ip_address');
            $table->timestamp('attempted_at');
            $table->boolean('successful')->default(false);
            $table->timestamps();
            
            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_login_attempts');
    }
};