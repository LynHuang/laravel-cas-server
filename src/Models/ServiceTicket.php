<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * ST (Service Ticket) 模型
 * 
 * ST是一次性使用的票据，用于客户端应用验证用户身份：
 * - 由TGT生成，关联到特定的客户端应用
 * - 生命周期很短（通常几分钟）
 * - 使用后立即销毁（一次性使用）
 * - 用于客户端验证用户身份
 * 
 * @property int $id ST ID
 * @property string $st ST票据字符串
 * @property string $tgt 关联的TGT票据
 * @property int $client_id 客户端ID
 * @property Carbon $expire_at 过期时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class ServiceTicket extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_service_tickets';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'st',
        'tgt',
        'client_id',
        'expire_at'
    ];
    
    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'client_id' => 'integer',
        'expire_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
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
     * 获取关联的客户端
     * 
     * @return BelongsTo
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
    
    /**
     * 检查ST是否过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }
    
    /**
     * 检查ST是否有效
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }
    
    /**
     * 检查ST是否已被消费
     * ST票据是一次性使用的，使用后会被删除
     * 
     * @return bool
     */
    public function isConsumed(): bool
    {
        // ST票据使用后会被删除，所以如果还存在就说明未被消费
        return false;
    }
    
    /**
     * 获取ST剩余有效时间（秒）
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
     * 验证ST是否属于指定客户端
     * 
     * @param int $clientId 客户端ID
     * @return bool
     */
    public function belongsToClient(int $clientId): bool
    {
        return $this->client_id === $clientId;
    }
    
    /**
     * 获取关联的用户信息
     * 
     * @return mixed|null
     */
    public function getUser()
    {
        $tgt = $this->ticketGrantingTicket;
        if (!$tgt) {
            return null;
        }
        
        return $tgt->getUser();
    }
    
    /**
     * 获取用户ID
     * 
     * @return string|null
     */
    public function getUserId(): ?string
    {
        $tgt = $this->ticketGrantingTicket;
        return $tgt ? $tgt->user_id : null;
    }
    
    /**
     * 验证并消费ST票据
     * ST票据是一次性使用的，验证后应立即删除
     * 
     * @param int|null $clientId 客户端ID（可选验证）
     * @return array|null 验证结果
     */
    public function validateAndConsume(?int $clientId = null): ?array
    {
        // 检查票据是否过期
        if ($this->isExpired()) {
            return null;
        }
        
        // 检查客户端ID是否匹配
        if ($clientId && !$this->belongsToClient($clientId)) {
            return null;
        }
        
        // 获取用户信息
        $user = $this->getUser();
        if (!$user) {
            return null;
        }
        
        // 记录验证信息
        $validationData = [
            'user_id' => $this->getUserId(),
            'user' => $user,
            'client_id' => $this->client_id,
            'tgt' => $this->tgt,
            'validated_at' => Carbon::now()
        ];
        
        // 删除ST票据（一次性使用）
        $this->delete();
        
        return $validationData;
    }
    
    /**
     * 查询作用域：仅有效的ST
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('expire_at', '>', Carbon::now());
    }
    
    /**
     * 查询作用域：已过期的ST
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_at', '<=', Carbon::now());
    }
    
    /**
     * 查询作用域：按客户端ID查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $clientId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
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
     * 查询作用域：按ST字符串查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $st
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySt($query, string $st)
    {
        return $query->where('st', $st);
    }
    
    /**
     * 获取ST的类型标识
     * 
     * @return string
     */
    public function getTypeAttribute(): string
    {
        return 'service_ticket';
    }
    
    /**
     * 获取ST的简短描述
     * 
     * @return string
     */
    public function getDescriptionAttribute(): string
    {
        $clientName = $this->client ? $this->client->client_name : 'Unknown';
        return "Service Ticket for {$clientName}";
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
        $array['is_valid'] = $this->isValid();
        $array['remaining_time'] = $this->getRemainingTime();
        $array['type'] = $this->getTypeAttribute();
        $array['description'] = $this->getDescriptionAttribute();
        
        return $array;
    }
}