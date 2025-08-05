<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * CAS票据基础模型
 * 
 * 这是一个基础的票据模型，主要用于向后兼容
 * 实际的票据管理应该使用具体的票据模型：
 * - TicketGrantingTicket (TGT)
 * - ServiceTicket (ST)
 * - ProxyGrantingTicket (PGT)
 * - ProxyTicket (PT)
 * 
 * @property int $id 票据ID
 * @property string $user_id 用户ID
 * @property string $ticket 票据字符串
 * @property Carbon $expire_at 过期时间
 * @deprecated 建议使用具体的票据模型
 */
class Ticket extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_tickets';

    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'user_id', 'ticket', 'expire_at'
    ];

    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'expire_at' => 'datetime',
    ];

    /**
     * 是否使用时间戳
     * 
     * @var bool
     */
    public $timestamps = false;
    
    /**
     * 检查票据是否过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }
    
    /**
     * 检查票据是否有效
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    /**
     * 获取票据剩余有效时间（秒）
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
     * 查询作用域：仅有效的票据
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('expire_at', '>', Carbon::now());
    }
    
    /**
     * 查询作用域：已过期的票据
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
}