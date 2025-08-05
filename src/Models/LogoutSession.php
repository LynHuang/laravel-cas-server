<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 单点登出会话模型
 * 
 * 管理用户在各个客户端应用的登录会话：
 * - 记录用户在不同客户端的登录状态
 * - 支持单点登出功能
 * - 维护会话的生命周期
 * - 提供会话查询和管理功能
 * 
 * @property int $id 会话ID
 * @property string $user_id 用户ID
 * @property string $session_id 会话ID
 * @property string $client_name 客户端名称
 * @property string|null $tgt 关联的TGT票据
 * @property Carbon|null $login_at 登录时间
 * @property bool $is_active 是否活跃
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class LogoutSession extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_logout_sessions';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'user_id',
        'session_id',
        'client_name',
        'tgt',
        'login_at',
        'is_active'
    ];
    
    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
        'login_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 获取关联的客户端
     * 
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_name', 'client_name');
    }
    
    /**
     * 获取关联的TGT票据
     * 
     * @return BelongsTo
     */
    public function ticketGrantingTicket(): BelongsTo
    {
        return $this->belongsTo(TicketGrantingTicket::class, 'tgt', 'tgt');
    }
    
    /**
     * 获取用户信息
     * 
     * @return mixed
     */
    public function getUser()
    {
        $userModel = config('casserver.user_model', 'App\\Models\\User');
        return $userModel::find($this->user_id);
    }
    
    /**
     * 检查会话是否活跃
     * 
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }
    
    /**
     * 激活会话
     * 
     * @return bool
     */
    public function activate(): bool
    {
        return $this->update([
            'is_active' => true,
            'login_at' => Carbon::now()
        ]);
    }
    
    /**
     * 停用会话
     * 
     * @return bool
     */
    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }
    
    /**
     * 获取会话持续时间（秒）
     * 
     * @return int
     */
    public function getDuration(): int
    {
        if (!$this->login_at) {
            return 0;
        }
        
        $endTime = $this->is_active ? Carbon::now() : $this->updated_at;
        return $this->login_at->diffInSeconds($endTime);
    }
    
    /**
     * 获取会话持续时间的人类可读格式
     * 
     * @return string
     */
    public function getDurationForHumans(): string
    {
        if (!$this->login_at) {
            return '未知';
        }
        
        $endTime = $this->is_active ? Carbon::now() : $this->updated_at;
        return $this->login_at->diffForHumans($endTime, true);
    }
    
    /**
     * 检查会话是否过期
     * 基于配置的会话超时时间
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->is_active || !$this->login_at) {
            return true;
        }
        
        $timeoutMinutes = config('casserver.session_timeout', 120); // 默认2小时
        $expireTime = $this->login_at->addMinutes($timeoutMinutes);
        
        return Carbon::now()->isAfter($expireTime);
    }
    
    /**
     * 更新会话的最后活动时间
     * 
     * @return bool
     */
    public function touch(): bool
    {
        if (!$this->is_active) {
            return false;
        }
        
        return $this->update(['updated_at' => Carbon::now()]);
    }
    
    /**
     * 获取客户端的登出回调URL
     * 
     * @return string|null
     */
    public function getLogoutCallbackUrl(): ?string
    {
        $client = $this->client;
        return $client ? $client->client_logout_callback : null;
    }
    
    /**
     * 执行单点登出
     * 向客户端发送登出通知
     * 
     * @return bool
     */
    public function performSingleLogout(): bool
    {
        $callbackUrl = $this->getLogoutCallbackUrl();
        
        if (!$callbackUrl) {
            // 如果没有回调URL，直接停用会话
            return $this->deactivate();
        }
        
        try {
            // 发送登出通知到客户端
            $this->sendLogoutNotification($callbackUrl);
            
            // 停用会话
            return $this->deactivate();
        } catch (\Exception $e) {
            // 记录错误日志
            \Log::error('单点登出失败', [
                'session_id' => $this->id,
                'client_name' => $this->client_name,
                'callback_url' => $callbackUrl,
                'error' => $e->getMessage()
            ]);
            
            // 即使通知失败，也要停用会话
            return $this->deactivate();
        }
    }
    
    /**
     * 发送登出通知到客户端
     * 
     * @param string $callbackUrl 回调URL
     * @throws \Exception
     */
    protected function sendLogoutNotification(string $callbackUrl): void
    {
        // 构建登出请求参数
        $params = [
            'logoutRequest' => $this->buildLogoutRequest(),
            'service' => $this->client_name
        ];
        
        // 发送HTTP POST请求
        $client = new \GuzzleHttp\Client([
            'timeout' => config('casserver.logout_timeout', 10),
            'verify' => config('casserver.ssl_verify', true)
        ]);
        
        $response = $client->post($callbackUrl, [
            'form_params' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => 'Laravel-CAS-Server/1.0'
            ]
        ]);
        
        // 检查响应状态
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('登出通知响应状态码: ' . $response->getStatusCode());
        }
    }
    
    /**
     * 构建SAML登出请求
     * 
     * @return string
     */
    protected function buildLogoutRequest(): string
    {
        $sessionIndex = $this->session_id;
        $nameId = $this->user_id;
        $issueInstant = Carbon::now()->toISOString();
        
        return sprintf(
            '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ID="%s" IssueInstant="%s" Version="2.0">'
            . '<saml:NameID xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">%s</saml:NameID>'
            . '<samlp:SessionIndex>%s</samlp:SessionIndex>'
            . '</samlp:LogoutRequest>',
            uniqid('logout_'),
            $issueInstant,
            htmlspecialchars($nameId),
            htmlspecialchars($sessionIndex)
        );
    }
    
    /**
     * 查询作用域：仅活跃的会话
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * 查询作用域：非活跃的会话
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }
    
    /**
     * 查询作用域：按用户ID查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    /**
     * 查询作用域：按客户端名称查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $clientName
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByClient($query, string $clientName)
    {
        return $query->where('client_name', $clientName);
    }
    
    /**
     * 查询作用域：按TGT查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tgt
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTgt($query, string $tgt)
    {
        return $query->where('tgt', $tgt);
    }
    
    /**
     * 查询作用域：过期的会话
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        $timeoutMinutes = config('casserver.session_timeout', 120);
        $expireTime = Carbon::now()->subMinutes($timeoutMinutes);
        
        return $query->where('is_active', true)
                    ->where('login_at', '<', $expireTime);
    }
    
    /**
     * 转换为数组时的自定义格式
     * 
     * @return array
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        
        // 添加计算属性
        $array['duration'] = $this->getDuration();
        $array['duration_for_humans'] = $this->getDurationForHumans();
        $array['is_expired'] = $this->isExpired();
        $array['supports_slo'] = !empty($this->getLogoutCallbackUrl());
        
        return $array;
    }
}