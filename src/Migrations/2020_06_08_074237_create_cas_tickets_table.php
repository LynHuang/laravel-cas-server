<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAS票据相关数据表迁移
 * 
 * 本迁移文件创建CAS协议所需的所有票据表：
 * 1. cas_ticket_granting_tickets - TGT票据表，用于存储用户的主要认证票据
 * 2. cas_service_tickets - ST票据表，用于客户端应用验证用户身份
 * 3. cas_proxy_tickets - PT票据表，用于代理认证（可选功能）
 * 4. cas_proxy_granting_tickets - PGT票据表，用于代理票据授权（可选功能）
 * 5. cas_ticket_validation_records - 票据验证记录表，用于审计和日志
 */
class CreateCasTicketsTable extends Migration
{
    /**
     * Run the migrations.
     * 创建CAS协议所需的所有票据表
     *
     * @return void
     */
    public function up()
    {
        // 创建TGT（Ticket Granting Ticket）表
        // TGT是CAS协议中的核心票据，代表用户已通过认证
        Schema::create('cas_ticket_granting_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('user_id')->comment('用户ID，关联到Laravel用户表');
            $table->string('tgt', 255)->unique()->comment('TGT票据字符串，全局唯一');
            $table->timestamp('expire_at')->comment('票据过期时间');
            $table->timestamps();
            
            // 添加索引以提高查询性能
            $table->index('user_id', 'idx_tgt_user_id');
            $table->index('expire_at', 'idx_tgt_expire_at');
        });

        // 创建ST（Service Ticket）表
        // ST是一次性使用的票据，用于客户端应用验证用户身份
        Schema::create('cas_service_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('st', 255)->unique()->comment('ST票据字符串，全局唯一');
            $table->string('tgt', 255)->comment('关联的TGT票据');
            $table->unsignedBigInteger('client_id')->comment('客户端应用ID');
            $table->timestamp('expire_at')->comment('票据过期时间');
            $table->timestamps();
            
            // 添加外键约束和索引
            $table->foreign('client_id')->references('id')->on('cas_clients')->onDelete('cascade');
            $table->index('tgt', 'idx_st_tgt');
            $table->index('client_id', 'idx_st_client_id');
            $table->index('expire_at', 'idx_st_expire_at');
        });

        // 创建PT（Proxy Ticket）表
        // PT用于代理认证场景，允许一个服务代表用户访问另一个服务
        Schema::create('cas_proxy_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('pt', 255)->unique()->comment('PT票据字符串，全局唯一');
            $table->string('pgt', 255)->comment('关联的PGT票据');
            $table->unsignedBigInteger('client_id')->comment('客户端应用ID');
            $table->timestamp('expire_at')->comment('票据过期时间');
            $table->timestamps();
            
            // 添加外键约束和索引
            $table->foreign('client_id')->references('id')->on('cas_clients')->onDelete('cascade');
            $table->index('pgt', 'idx_pt_pgt');
            $table->index('client_id', 'idx_pt_client_id');
            $table->index('expire_at', 'idx_pt_expire_at');
        });

        // 创建PGT（Proxy Granting Ticket）表
        // PGT用于生成PT票据，支持代理认证功能
        Schema::create('cas_proxy_granting_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('pgt', 255)->unique()->comment('PGT票据字符串，全局唯一');
            $table->string('tgt', 255)->comment('关联的TGT票据');
            $table->timestamp('expire_at')->comment('票据过期时间');
            $table->timestamps();
            
            // 添加索引
            $table->index('tgt', 'idx_pgt_tgt');
            $table->index('expire_at', 'idx_pgt_expire_at');
        });

        // 创建票据验证记录表
        // 用于记录所有票据验证操作，便于审计和调试
        Schema::create('cas_ticket_validation_records', function (Blueprint $table) {
            $table->id();
            $table->string('ticket', 255)->comment('被验证的票据');
            $table->unsignedBigInteger('client_id')->comment('验证票据的客户端ID');
            $table->timestamp('validated_at')->comment('验证时间');
            $table->timestamps();
            
            // 添加外键约束和索引
            $table->foreign('client_id')->references('id')->on('cas_clients')->onDelete('cascade');
            $table->index('ticket', 'idx_validation_ticket');
            $table->index('client_id', 'idx_validation_client_id');
            $table->index('validated_at', 'idx_validation_time');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cas_ticket_granting_tickets');
        Schema::dropIfExists('cas_service_tickets');
        Schema::dropIfExists('cas_proxy_tickets');
        Schema::dropIfExists('cas_proxy_granting_tickets');
        Schema::dropIfExists('cas_ticket_validation_records');
    }
}
