<?php

namespace Lyn\LaravelCasServer\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Services\TicketManger;

/**
 * CAS认证服务
 * 
 * 负责处理CAS协议中的用户认证逻辑：
 * 1. 用户登录验证
 * 2. 用户信息获取
 * 3. 会话管理
 * 4. 单点登录状态维护
 * 
 * 认证流程：
 * 1. 验证用户凭据（用户名/密码）
 * 2. 生成TGT票据
 * 3. 创建用户会话
 * 4. 记录登录日志
 */
class AuthService
{
    /**
     * 票据管理器实例
     * 
     * @var TicketManger
     */
    protected $ticketManager;
    
    /**
     * 构造函数
     * 
     * @param TicketManger $ticketManager 票据管理器
     */
    public function __construct(TicketManger $ticketManager)
    {
        $this->ticketManager = $ticketManager;
    }
    
    /**
     * 用户登录认证
     * 
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $client_name 客户端名称（可选）
     * @return array 认证结果
     */
    public function authenticate($username, $password, $client_name = null)
    {
        // 获取用户模型配置
        $userModel = config('casserver.user_model', 'App\\Models\\User');
        $usernameField = config('casserver.username_field', 'email');
        
        // 查找用户
        $user = $userModel::where($usernameField, $username)->first();
        
        if (!$user) {
            return [
                'success' => false,
                'message' => '用户不存在',
                'error_code' => 'USER_NOT_FOUND'
            ];
        }
        
        // 验证密码
        if (!Hash::check($password, $user->password)) {
            return [
                'success' => false,
                'message' => '密码错误',
                'error_code' => 'INVALID_PASSWORD'
            ];
        }
        
        // 检查用户状态（如果有相关字段）
        if (isset($user->status) && $user->status !== 'active') {
            return [
                'success' => false,
                'message' => '用户账户已被禁用',
                'error_code' => 'USER_DISABLED'
            ];
        }
        
        // 生成TGT票据
        $tgt = $this->ticketManager->generateTGT($user->id);
        
        // 创建用户会话
        $this->createUserSession($user->id, $tgt, $client_name);
        
        // 记录登录日志
        $this->recordLoginLog($user->id, $client_name);
        
        return [
            'success' => true,
            'message' => '认证成功',
            'user' => $user,
            'tgt' => $tgt,
            'user_id' => $user->id
        ];
    }
    
    /**
     * 通过TGT获取用户信息
     * 
     * @param string $tgt TGT票据
     * @return array|null 用户信息
     */
    public function getUserByTGT($tgt)
    {
        // 验证TGT票据
        if (!$this->ticketManager->validateTGT($tgt)) {
            return null;
        }
        
        // 获取TGT数据
        $tgtData = $this->ticketManager->getTGTData($tgt);
        if (!$tgtData) {
            return null;
        }
        
        // 获取用户信息
        $userModel = config('casserver.user_model', 'App\\Models\\User');
        $user = $userModel::find($tgtData['user_id']);
        
        if (!$user) {
            return null;
        }
        
        return $this->formatUserInfo($user);
    }
    
    /**
     * 通过ST票据获取用户信息
     * 
     * @param string $st ST票据
     * @param int $client_id 客户端ID（可选）
     * @return array|null 用户信息
     */
    public function getUserByST($st, $client_id = null)
    {
        // 验证ST票据
        if (!$this->ticketManager->validateST($st, $client_id)) {
            return null;
        }
        
        // 获取ST数据
        $stData = $this->ticketManager->getSTData($st);
        if (!$stData) {
            return null;
        }
        
        // ST票据使用后立即删除（一次性使用）
        $this->ticketManager->removeST($st);
        
        // 记录票据验证
        if ($client_id) {
            $this->ticketManager->recordTicketValidation($st, $client_id);
        }
        
        // 通过关联的TGT获取用户信息
        return $this->getUserByTGT($stData['tgt']);
    }
    
    /**
     * 检查用户是否已登录
     * 
     * @param string|int $user_id 用户ID
     * @return bool 是否已登录
     */
    public function isUserLoggedIn($user_id)
    {
        // 检查用户是否有有效的TGT票据
        $tgts = $this->ticketManager->getUserTGTs($user_id);
        return !empty($tgts);
    }
    
    /**
     * 用户登出
     * 
     * @param string|int $user_id 用户ID
     * @param string $client_name 客户端名称（可选）
     * @return bool 登出是否成功
     */
    public function logout($user_id, $client_name = null)
    {
        // 获取用户的所有TGT票据
        $tgts = $this->ticketManager->getUserTGTs($user_id);
        
        // 删除所有TGT票据（这会同时删除关联的ST票据）
        foreach ($tgts as $tgt) {
            $this->ticketManager->removeTGT($tgt);
        }
        
        // 更新登出会话状态
        $this->updateLogoutSessions($user_id, $client_name);
        
        // 记录登出日志
        $this->recordLogoutLog($user_id, $client_name);
        
        return true;
    }
    
    /**
     * 获取用户的有效TGT票据
     * 
     * @param string|int $user_id 用户ID
     * @return \Lyn\LaravelCasServer\Models\TicketGrantingTicket|null
     */
    public function getValidTGT($user_id)
    {
        $userModel = config('casserver.user_model', 'App\\Models\\User');
        $user = $userModel::find($user_id);
        
        if (!$user) {
            return null;
        }
        
        // 查找用户的有效TGT
        return \Lyn\LaravelCasServer\Models\TicketGrantingTicket::byUser($user_id)
            ->valid()
            ->first();
    }
    
    /**
     * 生成服务票据
     * 
     * @param string $tgt TGT票据
     * @param string $service 服务URL
     * @param string $client_name 客户端名称
     * @return string|null ST票据
     */
    public function generateServiceTicket($tgt, $service, $client_name)
    {
        // 验证TGT票据
        if (!$this->ticketManager->validateTGT($tgt)) {
            return null;
        }
        
        // 查找客户端
        $client = Client::where('client_name', $client_name)->first();
        if (!$client || !$client->client_enabled) {
            return null;
        }
        
        // 验证服务URL是否匹配客户端配置
        if (!$this->validateServiceUrl($service, $client->client_redirect)) {
            return null;
        }
        
        // 生成ST票据
        return $this->ticketManager->generateST($tgt, $client->id);
    }
    
    /**
     * 创建用户会话记录
     * 
     * @param string|int $user_id 用户ID
     * @param string $tgt TGT票据
     * @param string $client_name 客户端名称
     */
    protected function createUserSession($user_id, $tgt, $client_name = null)
    {
        if ($client_name) {
            DB::table('cas_logout_sessions')->insert([
                'user_id' => $user_id,
                'session_id' => session()->getId(),
                'client_name' => $client_name,
                'tgt' => $tgt,
                'login_at' => Carbon::now(),
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }
    
    /**
     * 更新登出会话状态
     * 
     * @param string|int $user_id 用户ID
     * @param string $client_name 客户端名称
     */
    protected function updateLogoutSessions($user_id, $client_name = null)
    {
        $query = DB::table('cas_logout_sessions')
            ->where('user_id', $user_id)
            ->where('is_active', true);
            
        if ($client_name) {
            $query->where('client_name', $client_name);
        }
        
        $query->update([
            'is_active' => false,
            'updated_at' => Carbon::now()
        ]);
    }
    
    /**
     * 验证服务URL
     * 
     * @param string $service 请求的服务URL
     * @param string $allowedUrl 允许的URL
     * @return bool 是否匹配
     */
    protected function validateServiceUrl($service, $allowedUrl)
    {
        // 简单的URL匹配验证
        // 可以根据需要实现更复杂的匹配逻辑
        $serviceParsed = parse_url($service);
        $allowedParsed = parse_url($allowedUrl);
        
        if (!$serviceParsed || !$allowedParsed) {
            return false;
        }
        
        // 检查主机名和端口
        return $serviceParsed['host'] === $allowedParsed['host'] &&
               ($serviceParsed['port'] ?? 80) === ($allowedParsed['port'] ?? 80);
    }
    
    /**
     * 格式化用户信息
     * 
     * @param mixed $user 用户模型实例
     * @return array 格式化的用户信息
     */
    protected function formatUserInfo($user)
    {
        // 获取配置的用户信息字段
        $userInfoFields = config('casserver.user_info_fields', [
            'id', 'name', 'email'
        ]);
        
        $userInfo = [];
        foreach ($userInfoFields as $field) {
            if (isset($user->$field)) {
                $userInfo[$field] = $user->$field;
            }
        }
        
        return $userInfo;
    }
    
    /**
     * 记录登录日志
     * 
     * @param string|int $user_id 用户ID
     * @param string $client_name 客户端名称
     */
    protected function recordLoginLog($user_id, $client_name = null)
    {
        // 这里可以实现登录日志记录逻辑
        // 例如记录到日志文件或数据库
        \Log::info('CAS用户登录', [
            'user_id' => $user_id,
            'client_name' => $client_name,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => Carbon::now()
        ]);
    }
    
    /**
     * 记录登出日志
     * 
     * @param string|int $user_id 用户ID
     * @param string $client_name 客户端名称
     */
    protected function recordLogoutLog($user_id, $client_name = null)
    {
        // 这里可以实现登出日志记录逻辑
        \Log::info('CAS用户登出', [
            'user_id' => $user_id,
            'client_name' => $client_name,
            'ip' => request()->ip(),
            'timestamp' => Carbon::now()
        ]);
    }
    
    /**
     * 获取用户的活跃会话列表
     * 
     * @param string|int $user_id 用户ID
     * @return array 活跃会话列表
     */
    public function getUserActiveSessions($user_id)
    {
        return DB::table('cas_logout_sessions')
            ->where('user_id', $user_id)
            ->where('is_active', true)
            ->orderBy('login_at', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * 清理过期会话
     * 定期清理数据库中的过期会话记录
     */
    public function cleanExpiredSessions()
    {
        // 清理超过配置时间的非活跃会话
        $expireHours = config('casserver.session_cleanup_hours', 24);
        $expireTime = Carbon::now()->subHours($expireHours);
        
        DB::table('cas_logout_sessions')
            ->where('is_active', false)
            ->where('updated_at', '<', $expireTime)
            ->delete();
    }
}