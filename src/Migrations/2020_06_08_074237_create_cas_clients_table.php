<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCasClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 32)->unique()->comment('客服端名称');
            $table->string('client_redirect')->comment('客服端回调');
            $table->string('client_logout_callback')->nullable()->comment('客户端单点登出控制');
            $table->boolean('client_enabled')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cas_clients');
    }
}
