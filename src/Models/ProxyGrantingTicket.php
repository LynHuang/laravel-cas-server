<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * 代理授权票据模型 (Proxy Granting Ticket - PGT)
 * 
 * 在CAS协议中，PGT用于代理认证场景：
 * - 由有效的ST验证后生成
 * - 允许服务代表用户获取其他服务的PT
 * - 具有较长的生命周期
 * - 支持生成多个PT
 * - 与原始ST保持关联
 * 
 * @property int $id 票据ID
 * @property string $pgt 代理授权票据字符串
 * @property string $pgt_iou PGT的IOU（I Owe You）标识
 * @property string $st 关联的服务票据
 * @property string $proxy_callback_url 代理回调URL
 * @property bool $is_consumed 是否已消费
 * @property Carbon $expire_at 过期时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class ProxyGrantingTicket extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_proxy_granting_tickets';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'pgt',
        'pgt_iou',
        'st',
        'proxy_callback_url',
        'is_consumed',
        'expire_at'
    ];
    
    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'is_consumed' => 'boolean',
        'expire_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 获取关联的服务票据
     * 
     * @return BelongsTo
     */
    public function serviceTicket(): BelongsTo
    {
        return $this->belongsTo(ServiceTicket::class, 'st', 'st');
    }
    
    /**
     * 获取关联的代理票据
     * 
     * @return HasMany
     */
    public function proxyTickets(): HasMany
    {
        return $this->hasMany(ProxyTicket::class, 'pgt', 'pgt');
    }
    
    /**
     * 检查票据是否有效
     * 有效条件：未过期且未被消费
     * 
     * @return bool
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->is_consumed;
    }
    
    /**
     * 检查票据是否过期
     * 
     * @return bool
     */
    public function isExpired(): bool
    {
        return Carbon::now()->isAfter($this->expire_at);
    }
    
    /**
     * 检查票据是否已被消费
     * 
     * @return bool
     */
    public function isConsumed(): bool
    {
        return $this->is_consumed;
    }
    
    /**
     * 消费票据
     * 标记PGT为已消费状态
     * 
     * @return bool
     */
    public function consume(): bool
    {
        if ($this->is_consumed) {
            return false;
        }
        
        return $this->update(['is_consumed' => true]);
    }
    
    /**
     * 获取剩余有效时间（秒）
     * 
     * @return int
     */
    public function getRemainingTime(): int
    {
        if ($this->isExpired()) {
            return 0;
        }
        
        return Carbon::now()->diffInSeconds($this->expire_at);
    }
    
    /**
     * 获取用户信息
     * 通过关联的ST获取用户信息
     * 
     * @return mixed|null
     */
    public function getUser()
    {
        $st = $this->serviceTicket;
        return $st ? $st->getUser() : null;
    }
    
    /**
     * 获取客户端信息
     * 通过关联的ST获取客户端信息
     * 
     * @return Client|null
     */
    public function getClient(): ?Client
    {
        $st = $this->serviceTicket;
        return $st ? $st->client : null;
    }
    
    /**
     * 生成代理票据
     * 为指定的目标服务生成PT
     * 
     * @param string $targetService 目标服务URL
     * @return ProxyTicket|null
     */
    public function generateProxyTicket(string $targetService): ?ProxyTicket
    {
        if (!$this->isValid()) {
            return null;
        }
        
        // 验证目标服务是否被允许
        if (!$this->isTargetServiceAllowed($targetService)) {
            return null;
        }
        
        // 构建代理链
        $proxyChain = $this->buildProxyChain($targetService);
        
        // 创建PT
        return ProxyTicket::createForPgt($this->pgt, $targetService, $proxyChain);
    }
    
    /**
     * 检查目标服务是否被允许
     * 
     * @param string $targetService
     * @return bool
     */
    protected function isTargetServiceAllowed(string $targetService): bool
    {
        $client = $this->getClient();
        if (!$client) {
            return false;
        }
        
        // 获取允许的代理目标配置
        $allowedProxies = config('casserver.allowed_proxy_chains', []);
        $clientName = $client->client_name;
        
        if (!isset($allowedProxies[$clientName])) {
            return false;
        }
        
        $allowedTargets = $allowedProxies[$clientName];
        
        // 检查目标服务是否在允许列表中
        foreach ($allowedTargets as $pattern) {
            if ($this->matchesPattern($targetService, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 构建代理链
     * 
     * @param string $targetService
     * @return array
     */
    protected function buildProxyChain(string $targetService): array
    {
        $chain = [];
        
        // 添加当前代理回调URL到代理链
        if ($this->proxy_callback_url) {
            $chain[] = $this->proxy_callback_url;
        }
        
        return $chain;
    }
    
    /**
     * 模式匹配辅助方法
     * 
     * @param string $url
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $url, string $pattern): bool
    {
        // 转换通配符为正则表达式
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/i', $url) === 1;
    }
    
    /**
     * 获取活跃的代理票据数量
     * 
     * @return int
     */
    public function getActiveProxyTicketsCount(): int
    {
        return $this->proxyTickets()->valid()->count();
    }
    
    /**
     * 获取所有代理票据（包括过期的）
     * 
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllProxyTickets()
    {
        return $this->proxyTickets;
    }
    
    /**
     * 清理过期的代理票据
     * 
     * @return int 清理的数量
     */
    public function cleanExpiredProxyTickets(): int
    {
        return $this->proxyTickets()->expired()->delete();
    }
    
    /**
     * 撤销所有关联的代理票据
     * 当PGT被撤销时调用
     * 
     * @return int 撤销的数量
     */
    public function revokeAllProxyTickets(): int
    {
        return $this->proxyTickets()->update(['is_consumed' => true]);
    }
    
    /**
     * 生成PGT票据字符串
     * 
     * @return string
     */
    public static function generatePgt(): string
    {
        $prefix = config('casserver.pgt_prefix', 'PGT');
        $random = bin2hex(random_bytes(20));
        $timestamp = time();
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * 生成PGT IOU字符串
     * 
     * @return string
     */
    public static function generatePgtIou(): string
    {
        $prefix = config('casserver.pgt_iou_prefix', 'PGTIOU');
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * 创建新的代理授权票据
     * 
     * @param string $st ST票据字符串
     * @param string $proxyCallbackUrl 代理回调URL
     * @return static|null
     */
    public static function createForSt(string $st, string $proxyCallbackUrl): ?self
    {
        // 验证ST是否存在且有效
        $stModel = ServiceTicket::where('st', $st)
            ->valid()
            ->first();
            
        if (!$stModel) {
            return null;
        }
        
        // 检查ST是否已经有关联的PGT
        $existingPgt = self::where('st', $st)->first();
        if ($existingPgt) {
            return $existingPgt;
        }
        
        // 生成PGT和PGT IOU
        $pgt = self::generatePgt();
        $pgtIou = self::generatePgtIou();
        
        // 计算过期时间
        $expireMinutes = config('casserver.pgt_expire_minutes', 240); // PGT默认4小时过期
        $expireAt = Carbon::now()->addMinutes($expireMinutes);
        
        // 创建PGT记录
        $pgtModel = self::create([
            'pgt' => $pgt,
            'pgt_iou' => $pgtIou,
            'st' => $st,
            'proxy_callback_url' => $proxyCallbackUrl,
            'is_consumed' => false,
            'expire_at' => $expireAt
        ]);
        
        return $pgtModel;
    }
    
    /**
     * 通过PGT IOU查找PGT
     * 
     * @param string $pgtIou
     * @return static|null
     */
    public static function findByPgtIou(string $pgtIou): ?self
    {
        return self::where('pgt_iou', $pgtIou)
            ->valid()
            ->first();
    }
    
    /**
     * 验证代理回调URL
     * 
     * @param string $callbackUrl
     * @return bool
     */
    public static function validateProxyCallbackUrl(string $callbackUrl): bool
    {
        // 检查URL格式
        if (!filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // 检查协议（必须是HTTPS）
        $scheme = parse_url($callbackUrl, PHP_URL_SCHEME);
        if (strtolower($scheme) !== 'https') {
            return false;
        }
        
        // 检查是否在允许的代理回调列表中
        $allowedCallbacks = config('casserver.allowed_proxy_callbacks', []);
        
        foreach ($allowedCallbacks as $pattern) {
            if (self::matchesUrlPattern($callbackUrl, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * URL模式匹配
     * 
     * @param string $url
     * @param string $pattern
     * @return bool
     */
    protected static function matchesUrlPattern(string $url, string $pattern): bool
    {
        $regex = str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/'));
        return preg_match('/^' . $regex . '$/i', $url) === 1;
    }
    
    /**
     * 查询作用域：有效的票据
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->where('expire_at', '>', Carbon::now())
                    ->where('is_consumed', false);
    }
    
    /**
     * 查询作用域：过期的票据
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('expire_at', '<=', Carbon::now());
    }
    
    /**
     * 查询作用域：已消费的票据
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConsumed($query)
    {
        return $query->where('is_consumed', true);
    }
    
    /**
     * 查询作用域：按ST查找
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
     * 查询作用域：按PGT字符串查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $pgt
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPgt($query, string $pgt)
    {
        return $query->where('pgt', $pgt);
    }
    
    /**
     * 查询作用域：按PGT IOU查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $pgtIou
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPgtIou($query, string $pgtIou)
    {
        return $query->where('pgt_iou', $pgtIou);
    }
    
    /**
     * 模型启动方法
     * 设置模型事件监听器
     */
    protected static function boot()
    {
        parent::boot();
        
        // 当PGT被删除时，同时删除关联的代理票据
        static::deleting(function ($pgt) {
            $pgt->proxyTickets()->delete();
        });
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
        $array['active_proxy_tickets_count'] = $this->getActiveProxyTicketsCount();
        
        // 隐藏敏感信息
        unset($array['pgt']);
        
        return $array;
    }
}