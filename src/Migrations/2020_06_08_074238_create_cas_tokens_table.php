<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAS客户端令牌表迁移
 * 
 * 创建用于存储客户端访问令牌的数据表，主要用于：
 * 1. 单点登出时通知客户端应用
 * 2. 跟踪用户在各个客户端的会话状态
 * 3. 管理令牌的生命周期
 */
class CreateCasTokensTable extends Migration
{
    /**
     * 执行迁移
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cas_tokens', function (Blueprint $table) {
            $table->id();
            
            // 会话标识
            $table->string('session_id', 255)->comment('会话ID');
            
            // 客户端标识
            $table->string('client_id', 255)->comment('客户端ID');
            
            // 令牌值
            $table->text('token')->comment('令牌值');
            
            // 用户标识
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            
            // 服务URL（可选）
            $table->text('service_url')->nullable()->comment('服务URL');
            
            // 过期时间
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            
            // 时间戳
            $table->timestamps();
            
            // 索引
            $table->index('session_id', 'idx_cas_tokens_session_id');
            $table->index('client_id', 'idx_cas_tokens_client_id');
            $table->index('user_id', 'idx_cas_tokens_user_id');
            $table->index('expires_at', 'idx_cas_tokens_expires_at');
            
            // 复合索引
            $table->index(['session_id', 'client_id'], 'idx_cas_tokens_session_client');
            $table->index(['user_id', 'client_id'], 'idx_cas_tokens_user_client');
            
            // 唯一约束：同一会话同一客户端只能有一个令牌
            $table->unique(['session_id', 'client_id'], 'uk_cas_tokens_session_client');
        });
    }

    /**
     * 回滚迁移
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cas_tokens');
    }
}