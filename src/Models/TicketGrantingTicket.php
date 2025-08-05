<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * TGT (Ticket Granting Ticket) 模型
 * 
 * TGT是CAS协议的核心票据，代表用户已通过认证：
 * - 用户成功登录后生成TGT
 * - TGT用于生成ST票据
 * - TGT具有较长的生命周期（通常几小时）
 * - 用户登出时TGT被销毁
 * 
 * @property int $id TGT ID
 * @property string $user_id 用户ID
 * @property string $tgt TGT票据字符串
 * @property Carbon $expire_at 过期时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class TicketGrantingTicket extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_ticket_granting_tickets';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'user_id',
        'tgt',
        'expire_at'
    ];
    
    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'expire_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 获取关联的服务票据
     * 
     * @return HasMany
     */
    public function serviceTickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class, 'tgt', 'tgt');
    }
    
    /**
     * 获取关联的代理授权票据
     * 
     * @return HasMany
     */
    public function proxyGrantingTickets(): HasMany
    {
        return $this->hasMany(ProxyGrantingTicket::class, 'tgt', 'tgt');
    }
    
    /**
     * 检查TGT是否过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }
    
    /**
     * 检查TGT是否有效
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    /**
     * 获取TGT剩余有效时间（秒）
     * 
     * @return int
     */
    public function getRemainingTime(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return $this->expire_at->diffInSeconds(Carbon::now());
    }
    
    /**
     * 延长TGT有效期
     * 
     * @param int $seconds 延长的秒数
     * @return bool
     */
    public function extend(int $seconds): bool
    {
        $newExpireAt = $this->expire_at->addSeconds($seconds);
        return $this->update(['expire_at' => $newExpireAt]);
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
     * 获取活跃的服务票据数量
     * 
     * @return int
     */
    public function getActiveServiceTicketsCount(): int
    {
        return $this->serviceTickets()
            ->where('expire_at', '>', Carbon::now())
            ->count();
    }
    
    /**
     * 清理过期的关联票据
     * 
     * @return int 清理的票据数量
     */
    public function cleanExpiredTickets(): int
    {
        $now = Carbon::now();
        $count = 0;
        
        // 清理过期的ST票据
        $count += $this->serviceTickets()
            ->where('expire_at', '<', $now)
            ->delete();
            
        // 清理过期的PGT票据
        $count += $this->proxyGrantingTickets()
            ->where('expire_at', '<', $now)
            ->delete();
            
        return $count;
    }
    
    /**
     * 查询作用域：仅有效的TGT
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('expire_at', '>', Carbon::now());
    }
    
    /**
     * 查询作用域：已过期的TGT
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_at', '<=', Carbon::now());
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
     * 查询作用域：按TGT字符串查找
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
     * 模型启动时的事件
     */
    protected static function boot()
    {
        parent::boot();
        
        // 删除TGT时，同时删除所有关联的票据
        static::deleting(function ($tgt) {
            $tgt->serviceTickets()->delete();
            $tgt->proxyGrantingTickets()->delete();
        });
    }
}