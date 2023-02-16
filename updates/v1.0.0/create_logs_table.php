<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('winter_sso_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('action');
            $table->string('user_type')->index();
            $table->string('user_id')->index();
            $table->string('provided_id')->nullable();
            $table->string('provided_email')->nullable();
            $table->string('ip')->nullable();
            $table->mediumText('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('winter_sso_logs');
    }
};
