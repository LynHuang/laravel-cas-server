<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAS客户端应用数据表迁移
 * 
 * 本迁移文件创建CAS客户端应用表，用于管理接入CAS认证的各个应用系统：
 * - 存储客户端应用的基本信息
 * - 配置回调地址和登出回调
 * - 管理客户端的启用状态
 * - 支持客户端密钥验证（可选）
 */
class CreateCasClientsTable extends Migration
{
    /**
     * Run the migrations.
     * 创建CAS客户端应用表
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_clients', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 100)->comment('客户端应用名称，用于标识不同的应用系统');
            $table->string('client_redirect', 500)->comment('客户端重定向地址，用户认证成功后跳转的URL');
            $table->string('client_logout_callback', 500)->nullable()->comment('单点登出回调地址，可选');
            $table->string('client_secret', 255)->nullable()->comment('客户端密钥，用于增强安全性（可选）');
            $table->text('client_description')->nullable()->comment('客户端应用描述信息');
            $table->boolean('client_enabled')->default(true)->comment('客户端启用状态，false表示禁用该客户端');
            $table->timestamps();
            
            // 添加索引以提高查询性能
            $table->index('client_name', 'idx_client_name');
            $table->index('client_enabled', 'idx_client_enabled');
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
