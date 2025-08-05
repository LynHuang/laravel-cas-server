<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 客户端令牌模型
 * 
 * 用于存储和管理客户端应用的访问令牌，主要用于：
 * 1. 单点登出时通知客户端应用
 * 2. 跟踪用户在各个客户端的会话状态
 * 3. 管理令牌的生命周期
 * 
 * @property int $id 主键ID
 * @property string $session_id 会话ID
 * @property string $client_id 客户端ID
 * @property string $token 令牌值
 * @property mixed $user_id 用户ID
 * @property string|null $service_url 服务URL
 * @property \Carbon\Carbon $expires_at 过期时间
 * @property \Carbon\Carbon $created_at 创建时间
 * @property \Carbon\Carbon $updated_at 更新时间
 * 
 * @property-read Client $client 关联的客户端
 * @property-read \Illuminate\Database\Eloquent\Model $user 关联的用户
 */
class Token extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_tokens';

    /**
     * 可批量赋值的属性
     * 
     * @var array
     */
    protected $fillable = [
        'session_id',
        'client_id', 
        'token',
        'user_id',
        'service_url',
        'expires_at'
    ];

    /**
     * 属性类型转换
     * 
     * @var array
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * 隐藏的属性（序列化时不包含）
     * 
     * @var array
     */
    protected $hidden = [
        'token' // 出于安全考虑，默认隐藏令牌值
    ];

    /**
     * 获取关联的客户端
     * 
     * @return BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id', 'name');
    }

    /**
     * 获取关联的用户
     * 
     * @return BelongsTo
     */
    public function user()
    {
        $userModel = config('casserver.user_model', '\\App\\Models\\User');
        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * 检查令牌是否已过期
     * 
     * @return bool
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * 检查令牌是否有效
     * 
     * @return bool
     */
    public function isValid()
    {
        return !$this->isExpired();
    }

    /**
     * 获取令牌的剩余有效时间（秒）
     * 
     * @return int
     */
    public function getRemainingTime()
    {
        if (!$this->expires_at) {
            return 0;
        }
        
        $remaining = $this->expires_at->diffInSeconds(now(), false);
        return max(0, $remaining);
    }

    /**
     * 延长令牌有效期
     * 
     * @param int $seconds 延长的秒数
     * @return bool
     */
    public function extend($seconds = 3600)
    {
        $this->expires_at = $this->expires_at ? $this->expires_at->addSeconds($seconds) : now()->addSeconds($seconds);
        return $this->save();
    }

    /**
     * 撤销令牌（设置为已过期）
     * 
     * @return bool
     */
    public function revoke()
    {
        $this->expires_at = now()->subSecond();
        return $this->save();
    }

    /**
     * 查询作用域：有效的令牌
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * 查询作用域：已过期的令牌
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * 查询作用域：根据会话ID
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $sessionId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * 查询作用域：根据用户ID
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 查询作用域：根据客户端ID
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $clientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    /**
     * 生成安全的令牌值
     * 
     * @param int $length 令牌长度
     * @return string
     */
    public static function generateToken($length = 64)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * 清理过期令牌
     * 
     * @return int 清理的数量
     */
    public static function cleanExpired()
    {
        return static::expired()->delete();
    }

    /**
     * 获取用户在指定客户端的有效令牌
     * 
     * @param mixed $userId 用户ID
     * @param string $clientId 客户端ID
     * @return static|null
     */
    public static function getUserClientToken($userId, $clientId)
    {
        return static::valid()
            ->byUser($userId)
            ->byClient($clientId)
            ->first();
    }

    /**
     * 创建或更新用户客户端令牌
     * 
     * @param array $data 令牌数据
     * @return static
     */
    public static function createOrUpdateToken(array $data)
    {
        return static::updateOrCreate(
            [
                'session_id' => $data['session_id'],
                'client_id' => $data['client_id']
            ],
            array_merge($data, [
                'token' => $data['token'] ?? static::generateToken(),
                'expires_at' => $data['expires_at'] ?? now()->addHour()
            ])
        );
    }
}