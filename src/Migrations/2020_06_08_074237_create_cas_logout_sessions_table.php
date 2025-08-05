<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAS单点登出会话数据表迁移
 * 
 * 本迁移文件创建CAS单点登出会话表，用于管理用户的登录会话：
 * - 记录用户在各个客户端应用的登录会话
 * - 支持单点登出功能，当用户从一个应用登出时，自动登出所有应用
 * - 维护会话状态，便于会话管理和清理
 */
class CreateCasLogoutSessionsTable extends Migration
{
    /**
     * Run the migrations.
     * 创建CAS单点登出会话表
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_logout_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->comment('用户ID，关联到Laravel用户表');
            $table->string('session_id', 255)->comment('会话ID，用于标识用户在特定客户端的会话');
            $table->string('client_name', 100)->comment('客户端应用名称，标识用户登录的应用');
            $table->string('tgt', 255)->nullable()->comment('关联的TGT票据，用于会话验证');
            $table->timestamp('login_at')->nullable()->comment('登录时间');
            $table->boolean('is_active')->default(true)->comment('会话是否活跃，false表示已登出');
            $table->timestamps();
            
            // 添加索引以提高查询性能
            $table->index('user_id', 'idx_logout_user_id');
            $table->index('session_id', 'idx_logout_session_id');
            $table->index('client_name', 'idx_logout_client_name');
            $table->index('tgt', 'idx_logout_tgt');
            $table->index('is_active', 'idx_logout_active');
            
            // 添加复合索引
            $table->index(['user_id', 'client_name'], 'idx_logout_user_client');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {}
}
