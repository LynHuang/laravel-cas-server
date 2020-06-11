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

        Schema::create('cas_tokens', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->comment('登录用户id');
            $table->integer('client_id')->comment('客户端id');
            $table->string('server_session_id')->comment('服务器session_id');
            $table->string('client_token')->comment('客户端登录凭证');
            $table->timestamps();
        });

        Schema::table(config('casserver.user.table'), function (Blueprint $table) {
            $table->string('last_session_id')->comment('上次登录session_id');
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
        Schema::dropIfExists('cas_token_cache');
        Schema::table(config('casserver.user.table'), function (Blueprint $table) {
            $table->dropColumn('last_session_id');
        });
    }
}
