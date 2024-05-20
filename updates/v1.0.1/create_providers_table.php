<?php

use Winter\Storm\Database\Schema\Blueprint;
use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('winter_sso_providers', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('is_enabled')->nullable();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('client_id');
            $table->string('client_secret');
            $table->string('scopes')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('winter_sso_providers');
    }
};
