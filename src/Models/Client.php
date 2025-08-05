<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Carbon\Carbon;

/**
 * CAS客户端模型
 * 
 * 管理接入CAS认证系统的客户端应用信息：
 * - 客户端基本信息（名称、描述等）
 * - 回调地址配置（登录成功、登出回调）
 * - 安全配置（密钥、启用状态）
 * - 关联的票据和会话记录
 * 
 * @property int $id 客户端ID
 * @property string $client_name 客户端名称
 * @property string $client_redirect 重定向地址
 * @property string|null $client_logout_callback 登出回调地址
 * @property string|null $client_secret 客户端密钥
 * @property string|null $client_description 客户端描述
 * @property bool $client_enabled 是否启用
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class Client extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_clients';

    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'client_name',
        'client_redirect', 
        'client_logout_callback',
        'client_secret',
        'client_description',
        'client_enabled'
    ];

    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'client_enabled' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 隐藏的字段（不在序列化中显示）
     * 
     * @var array
     */
    protected $hidden = [
        'client_secret'
    ];
    
    /**
     * 获取客户端的所有服务票据
     * 
     * @return HasMany
     */
    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'client_id');
    }
    
    /**
     * 获取客户端的所有代理票据
     * 
     * @return HasMany
     */
    public function proxyTickets(): HasMany
    {
        return $this->hasMany(ProxyTicket::class, 'client_id');
    }
    
    /**
     * 获取关联的代理授权票据
     * 通过服务票据的关联获取
     * 
     * @return HasManyThrough
     */
    public function proxyGrantingTickets()
    {
        return $this->hasManyThrough(
            ProxyGrantingTicket::class,
            ServiceTicket::class,
            'client_name', // 服务票据表的外键
            'st', // 代理授权票据表的外键
            'client_name', // 客户端表的本地键
            'st' // 服务票据表的本地键
        );
    }
    
    /**
     * 获取客户端的票据验证记录
     * 
     * @return HasMany
     */
    public function validationRecords(): HasMany
    {
        return $this->hasMany(TicketValidationRecord::class, 'client_id');
    }
    
    /**
     * 获取客户端的登出会话记录
     * 
     * @return HasMany
     */
    public function logoutSessions(): HasMany
    {
        return $this->hasMany(LogoutSession::class, 'client_name', 'client_name');
    }
    
    /**
     * 检查客户端是否启用
     * 
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->client_enabled;
    }
    
    /**
     * 启用客户端
     * 
     * @return bool
     */
    public function enable(): bool
    {
        return $this->update(['client_enabled' => true]);
    }
    
    /**
     * 禁用客户端
     * 
     * @return bool
     */
    public function disable(): bool
    {
        return $this->update(['client_enabled' => false]);
    }
    
    /**
     * 验证重定向URL是否匹配
     * 
     * @param string $url 要验证的URL
     * @return bool
     */
    public function validateRedirectUrl(string $url): bool
    {
        // 解析配置的重定向URL和请求的URL
        $configuredUrl = parse_url($this->client_redirect);
        $requestUrl = parse_url($url);
        
        if (!$configuredUrl || !$requestUrl) {
            return false;
        }
        
        // 检查协议、主机名和端口是否匹配
        return ($configuredUrl['scheme'] ?? 'http') === ($requestUrl['scheme'] ?? 'http') &&
               $configuredUrl['host'] === $requestUrl['host'] &&
               ($configuredUrl['port'] ?? 80) === ($requestUrl['port'] ?? 80);
    }
    
    /**
     * 检查URL是否为有效的重定向URL
     * 
     * @param string $url 要验证的URL
     * @return bool
     */
    public function isValidRedirectUrl(string $url): bool
    {
        return $this->validateRedirectUrl($url);
    }
    
    /**
     * 验证客户端密钥
     * 
     * @param string $secret 要验证的密钥
     * @return bool
     */
    public function validateSecret(string $secret): bool
    {
        if (empty($this->client_secret)) {
            // 如果没有设置密钥，则不需要验证
            return true;
        }
        
        return hash_equals($this->client_secret, $secret);
    }
    
    /**
     * 生成新的客户端密钥
     * 
     * @return string
     */
    public function generateSecret(): string
    {
        $secret = bin2hex(random_bytes(32));
        $this->update(['client_secret' => $secret]);
        return $secret;
    }
    
    /**
     * 获取客户端的活跃票据数量
     * 
     * @return int
     */
    public function getActiveTicketsCount(): int
    {
        $now = Carbon::now();
        
        $stCount = \DB::table('cas_service_tickets')
            ->where('client_id', $this->id)
            ->where('expire_at', '>', $now)
            ->count();
            
        $ptCount = \DB::table('cas_proxy_tickets')
            ->where('client_id', $this->id)
            ->where('expire_at', '>', $now)
            ->count();
            
        return $stCount + $ptCount;
    }
    
    /**
     * 获取客户端的验证统计信息
     * 
     * @param int $days 统计天数，默认30天
     * @return array
     */
    public function getValidationStats(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $totalValidations = \DB::table('cas_ticket_validation_records')
            ->where('client_id', $this->id)
            ->where('validated_at', '>=', $startDate)
            ->count();
            
        $dailyStats = \DB::table('cas_ticket_validation_records')
            ->where('client_id', $this->id)
            ->where('validated_at', '>=', $startDate)
            ->selectRaw('DATE(validated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
            
        return [
            'total_validations' => $totalValidations,
            'daily_stats' => $dailyStats,
            'average_per_day' => $days > 0 ? round($totalValidations / $days, 2) : 0
        ];
    }
    
    /**
     * 清理客户端的过期票据
     * 
     * @return int 清理的票据数量
     */
    public function cleanExpiredTickets(): int
    {
        $now = Carbon::now();
        $count = 0;
        
        // 清理过期的ST票据
        $count += \DB::table('cas_service_tickets')
            ->where('client_id', $this->id)
            ->where('expire_at', '<', $now)
            ->delete();
            
        // 清理过期的PT票据
        $count += \DB::table('cas_proxy_tickets')
            ->where('client_id', $this->id)
            ->where('expire_at', '<', $now)
            ->delete();
            
        return $count;
    }
    
    /**
     * 查询作用域：仅启用的客户端
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('client_enabled', true);
    }
    
    /**
     * 查询作用域：按名称查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $name
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByName($query, string $name)
    {
        return $query->where('client_name', $name);
    }
    
    /**
     * 获取客户端的显示名称
     * 
     * @return string
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->client_description ?: $this->client_name;
    }
    
    /**
     * 检查是否支持单点登出
     * 
     * @return bool
     */
    public function supportsSingleLogout(): bool
    {
        return !empty($this->client_logout_callback);
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
        $array['display_name'] = $this->getDisplayNameAttribute();
        $array['supports_slo'] = $this->supportsSingleLogout();
        $array['active_tickets_count'] = $this->getActiveTicketsCount();
        
        return $array;
    }
}
