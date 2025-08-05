<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 代理票据模型 (Proxy Ticket - PT)
 * 
 * 在CAS协议中，PT用于代理认证场景：
 * - 由PGT（代理授权票据）生成
 * - 允许服务代表用户访问其他服务
 * - 支持多级代理链
 * - 具有较短的生命周期
 * - 一次性使用，验证后即失效
 * 
 * @property int $id 票据ID
 * @property string $pt 代理票据字符串
 * @property string $pgt 关联的代理授权票据
 * @property string $target_service 目标服务URL
 * @property string|null $proxy_chain 代理链信息
 * @property bool $is_consumed 是否已消费
 * @property Carbon $expire_at 过期时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class ProxyTicket extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_proxy_tickets';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'pt',
        'pgt',
        'target_service',
        'proxy_chain',
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
     * 获取关联的代理授权票据
     * 
     * @return BelongsTo
     */
    public function proxyGrantingTicket(): BelongsTo
    {
        return $this->belongsTo(ProxyGrantingTicket::class, 'pgt', 'pgt');
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
     * PT是一次性票据，验证后即标记为已消费
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
     * 通过PGT获取关联的用户信息
     * 
     * @return mixed|null
     */
    public function getUser()
    {
        $pgt = $this->proxyGrantingTicket;
        return $pgt ? $pgt->getUser() : null;
    }
    
    /**
     * 获取原始服务票据
     * 通过PGT获取关联的原始ST
     * 
     * @return ServiceTicket|null
     */
    public function getOriginalServiceTicket(): ?ServiceTicket
    {
        $pgt = $this->proxyGrantingTicket;
        return $pgt ? $pgt->serviceTicket : null;
    }
    
    /**
     * 获取代理链数组
     * 解析proxy_chain字段为数组
     * 
     * @return array
     */
    public function getProxyChainArray(): array
    {
        if (empty($this->proxy_chain)) {
            return [];
        }
        
        return explode(',', $this->proxy_chain);
    }
    
    /**
     * 设置代理链
     * 将代理链数组转换为字符串存储
     * 
     * @param array $proxyChain
     * @return void
     */
    public function setProxyChain(array $proxyChain): void
    {
        $this->proxy_chain = implode(',', $proxyChain);
    }
    
    /**
     * 添加代理到代理链
     * 
     * @param string $proxyUrl
     * @return void
     */
    public function addToProxyChain(string $proxyUrl): void
    {
        $chain = $this->getProxyChainArray();
        $chain[] = $proxyUrl;
        $this->setProxyChain($chain);
    }
    
    /**
     * 获取代理链长度
     * 
     * @return int
     */
    public function getProxyChainLength(): int
    {
        return count($this->getProxyChainArray());
    }
    
    /**
     * 检查是否为直接代理
     * 代理链长度为1表示直接代理
     * 
     * @return bool
     */
    public function isDirectProxy(): bool
    {
        return $this->getProxyChainLength() === 1;
    }
    
    /**
     * 检查是否为多级代理
     * 
     * @return bool
     */
    public function isChainedProxy(): bool
    {
        return $this->getProxyChainLength() > 1;
    }
    
    /**
     * 验证目标服务URL
     * 检查目标服务是否在允许的代理列表中
     * 
     * @return bool
     */
    public function validateTargetService(): bool
    {
        $pgt = $this->proxyGrantingTicket;
        if (!$pgt) {
            return false;
        }
        
        $originalSt = $pgt->serviceTicket;
        if (!$originalSt || !$originalSt->client) {
            return false;
        }
        
        // 检查客户端是否允许代理到目标服务
        $allowedProxies = config('casserver.allowed_proxy_chains', []);
        $clientName = $originalSt->client->client_name;
        
        if (!isset($allowedProxies[$clientName])) {
            return false;
        }
        
        $allowedTargets = $allowedProxies[$clientName];
        
        // 支持通配符匹配
        foreach ($allowedTargets as $pattern) {
            if ($this->matchesPattern($this->target_service, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 模式匹配辅助方法
     * 支持简单的通配符匹配
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
     * 生成PT票据字符串
     * 
     * @param string $targetService 目标服务URL
     * @return string
     */
    public static function generatePt(string $targetService): string
    {
        $prefix = config('casserver.pt_prefix', 'PT');
        $random = bin2hex(random_bytes(16));
        $hash = substr(md5($targetService . time()), 0, 8);
        
        return $prefix . '-' . $hash . '-' . $random;
    }
    
    /**
     * 创建新的代理票据
     * 
     * @param string $pgt PGT票据字符串
     * @param string $targetService 目标服务URL
     * @param array $proxyChain 代理链
     * @return static|null
     */
    public static function createForPgt(string $pgt, string $targetService, array $proxyChain = []): ?self
    {
        // 验证PGT是否存在且有效
        $pgtModel = ProxyGrantingTicket::where('pgt', $pgt)
            ->valid()
            ->first();
            
        if (!$pgtModel) {
            return null;
        }
        
        // 生成PT
        $pt = self::generatePt($targetService);
        
        // 计算过期时间
        $expireMinutes = config('casserver.pt_expire_minutes', 5); // PT默认5分钟过期
        $expireAt = Carbon::now()->addMinutes($expireMinutes);
        
        // 创建PT记录
        $proxyTicket = self::create([
            'pt' => $pt,
            'pgt' => $pgt,
            'target_service' => $targetService,
            'proxy_chain' => implode(',', $proxyChain),
            'is_consumed' => false,
            'expire_at' => $expireAt
        ]);
        
        return $proxyTicket;
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
     * 查询作用域：按PGT查找
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
     * 查询作用域：按PT字符串查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $pt
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPt($query, string $pt)
    {
        return $query->where('pt', $pt);
    }
    
    /**
     * 查询作用域：按目标服务查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $targetService
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTargetService($query, string $targetService)
    {
        return $query->where('target_service', $targetService);
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
        $array['proxy_chain_array'] = $this->getProxyChainArray();
        $array['proxy_chain_length'] = $this->getProxyChainLength();
        $array['is_direct_proxy'] = $this->isDirectProxy();
        $array['is_chained_proxy'] = $this->isChainedProxy();
        
        return $array;
    }
}