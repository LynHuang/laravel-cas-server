<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CAS Server Routes
|--------------------------------------------------------------------------
|
| 这里定义了CAS服务器的所有路由，包括：
| 1. 登录认证相关路由
| 2. 票据验证相关路由  
| 3. 代理票据相关路由
| 4. 用户信息相关路由
| 5. 密码重置相关路由
| 6. 单点登出相关路由
|
*/

// ============================================================================
// 登录认证路由 (Login Authentication Routes)
// ============================================================================

/**
 * GET /login - CAS登录页面
 * 显示登录表单，支持service参数指定登录成功后的重定向地址
 * 参数：service, renew, gateway
 */
Route::get('login', 'LoginController@getLogin')
    ->middleware('cas_auth')
    ->name('cas.login');

/**
 * POST /login - 处理登录请求
 * 验证用户凭据并生成TGT和ST
 */
Route::post('login', 'LoginController@postLogin')
    ->name('cas.post.login');

/**
 * GET /logout - CAS登出
 * 销毁TGT并执行单点登出，支持service参数指定登出后重定向地址
 * 参数：service, url
 */
Route::get('logout', 'LoginController@logout')
    ->name('cas.logout');

// ============================================================================
// 票据验证路由 (Ticket Validation Routes)
// ============================================================================

/**
 * GET /validate - CAS 1.0协议票据验证 (已废弃，仅为兼容性保留)
 * 验证Service Ticket的有效性
 * 参数：service, ticket
 */
Route::get('validate', 'AuthController@casValidate')
    ->name('cas.validate');

/**
 * GET /serviceValidate - CAS 2.0协议Service Ticket验证
 * 验证Service Ticket并返回用户信息
 * 参数：service, ticket, format(可选，支持XML/JSON)
 */
Route::get('serviceValidate', 'AuthController@serviceValidate')
    ->name('cas.service_validate');

/**
 * GET /proxyValidate - CAS 2.0协议Proxy Ticket验证
 * 验证Proxy Ticket并返回用户信息和代理链
 * 参数：service, ticket, format(可选，支持XML/JSON)
 */
Route::get('proxyValidate', 'AuthController@proxyValidate')
    ->name('cas.proxy_validate');

/**
 * GET /p3/serviceValidate - CAS 3.0协议Service Ticket验证
 * 扩展的票据验证，支持更多属性返回
 * 参数：service, ticket, format(可选)
 */
Route::get('p3/serviceValidate', 'AuthController@p3ServiceValidate')
    ->name('cas.p3_service_validate');

/**
 * GET /p3/proxyValidate - CAS 3.0协议Proxy Ticket验证
 * 扩展的代理票据验证
 * 参数：service, ticket, format(可选)
 */
Route::get('p3/proxyValidate', 'AuthController@p3ProxyValidate')
    ->name('cas.p3_proxy_validate');

// ============================================================================
// 代理票据路由 (Proxy Ticket Routes)
// ============================================================================

/**
 * GET /proxy - 代理票据生成
 * 使用PGT生成新的Proxy Ticket
 * 参数：pgt, targetService
 */
Route::get('proxy', 'AuthController@proxy')
    ->name('cas.proxy');

// ============================================================================
// 用户信息路由 (User Information Routes)
// ============================================================================

/**
 * GET /user/info - 获取用户信息
 * 根据有效票据返回用户详细信息
 * 需要cas_ticket_check中间件验证
 */
Route::get('user/info', 'AuthController@getUserInfo')
    ->middleware('cas_ticket_check')
    ->name('cas.user_info');

/**
 * POST /record-client-token - 记录客户端令牌
 * 用于单点登出时通知客户端应用
 * 需要cas_ticket_check中间件验证
 */
Route::post('record-client-token', 'AuthController@recordClientToken')
    ->middleware('cas_ticket_check')
    ->name('cas.record_token');

// ============================================================================
// 用户注册路由 (User Registration Routes) - 可选功能
// ============================================================================

/**
 * GET /register - 用户注册页面
 * 显示用户注册表单
 */
Route::get('register', 'AuthController@getRegister')
    ->middleware('cas_auth')
    ->name('cas.register');

/**
 * POST /register - 处理注册请求
 * 创建新用户账户
 */
Route::post('register', 'AuthController@postRegister')
    ->name('cas.post.register');

// ============================================================================
// 密码重置路由 (Password Reset Routes)
// ============================================================================

Route::group(['prefix' => 'password'], function () {
    /**
     * GET /password/forget - 忘记密码页面
     * 显示密码重置请求表单
     */
    Route::get('forget', 'PasswordController@passwordForget')
        ->name('cas.password.forget');
    
    /**
     * POST /password/send-code - 发送重置验证码
     * 向用户邮箱发送密码重置验证码
     */
    Route::post('send-code', 'PasswordController@passwordSendCode')
        ->name('cas.password.send_code');
    
    /**
     * GET /password/reset - 密码重置页面
     * 显示密码重置表单
     * 参数：token, email
     */
    Route::get('reset', 'PasswordController@passwordGetReset')
        ->name('cas.password.reset');
    
    /**
     * POST /password/reset - 处理密码重置
     * 验证重置令牌并更新密码
     */
    Route::post('reset', 'PasswordController@passwordPostReset')
        ->name('cas.password.post_reset');
    
    /**
     * POST /password/validate-strength - 密码强度验证
     * AJAX接口，实时验证密码强度
     */
    Route::post('validate-strength', 'PasswordController@validatePasswordStrength')
        ->name('cas.password.validate_strength');
});

// ============================================================================
// 管理和监控路由 (Management and Monitoring Routes) - 可选功能
// ============================================================================

Route::group(['prefix' => 'admin', 'middleware' => ['auth', 'can:manage-cas']], function () {
    /**
     * GET /admin/stats - CAS服务器统计信息
     * 显示票据统计、用户活动等信息
     */
    Route::get('stats', 'AdminController@stats')
        ->name('cas.admin.stats');
    
    /**
     * GET /admin/clients - 客户端管理
     * 管理注册的CAS客户端应用
     */
    Route::get('clients', 'AdminController@clients')
        ->name('cas.admin.clients');
    
    /**
     * POST /admin/clients/{client}/toggle - 启用/禁用客户端
     * 切换客户端的启用状态
     */
    Route::post('clients/{client}/toggle', 'AdminController@toggleClient')
        ->name('cas.admin.toggle_client');
    
    /**
     * DELETE /admin/tickets/expired - 清理过期票据
     * 手动清理过期的票据记录
     */
    Route::delete('tickets/expired', 'AdminController@cleanExpiredTickets')
        ->name('cas.admin.clean_tickets');
});

// ============================================================================
// API路由 (API Routes) - 用于第三方集成
// ============================================================================

Route::group(['prefix' => 'api/v1', 'middleware' => 'api'], function () {
    /**
     * POST /api/v1/validate-token - 令牌验证API
     * 供第三方应用验证CAS令牌
     */
    Route::post('validate-token', 'ApiController@validateToken')
        ->name('cas.api.validate_token');
    
    /**
     * GET /api/v1/user/{ticket} - 根据票据获取用户信息API
     * 供第三方应用获取用户详细信息
     */
    Route::get('user/{ticket}', 'ApiController@getUserByTicket')
        ->name('cas.api.user_by_ticket');
    
    /**
     * POST /api/v1/batch-validate - 批量验证票据API
     * 供第三方应用批量验证多个票据
     */
    Route::post('batch-validate', 'ApiController@batchValidateTokens')
        ->name('cas.api.batch_validate');
});

// ============================================================================
// 健康检查路由 (Health Check Routes)
// ============================================================================

/**
 * GET /health - 服务健康检查
 * 返回CAS服务器的运行状态
 */
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'version' => config('casserver.version', '1.0.0'),
        'uptime' => app()->hasBeenBootstrapped() ? 'running' : 'starting'
    ]);
})->name('cas.health');

/**
 * GET /version - 版本信息
 * 返回CAS服务器版本信息
 */
Route::get('version', function () {
    return response()->json([
        'name' => 'Laravel CAS Server',
        'version' => config('casserver.version', '1.0.0'),
        'protocol_versions' => ['1.0', '2.0', '3.0'],
        'features' => [
            'single_sign_on',
            'single_logout', 
            'proxy_authentication',
            'attribute_release',
            'password_reset'
        ]
    ]);
})->name('cas.version');