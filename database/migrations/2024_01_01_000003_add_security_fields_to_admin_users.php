<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->integer('failed_attempts')->default(0);
        });
    }

    public function down()
    {
        Schema::table('admin_users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_enabled', 'two_factor_secret', 'locked_until', 'failed_attempts']);
        });
    }
};