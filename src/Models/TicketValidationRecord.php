<?php

namespace Lyn\LaravelCasServer\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * 票据验证记录模型
 * 
 * 记录所有票据验证的详细信息：
 * - 跟踪票据验证历史
 * - 记录验证结果和错误信息
 * - 支持审计和安全分析
 * - 提供验证统计数据
 * - 帮助排查认证问题
 * 
 * @property int $id 记录ID
 * @property string $ticket 被验证的票据
 * @property string $ticket_type 票据类型（ST/PT/TGT）
 * @property string $client_name 客户端名称
 * @property string|null $service_url 服务URL
 * @property string|null $user_id 用户ID
 * @property bool $is_success 验证是否成功
 * @property string|null $error_code 错误代码
 * @property string|null $error_message 错误消息
 * @property string|null $client_ip 客户端IP地址
 * @property string|null $user_agent 用户代理
 * @property array|null $request_data 请求数据
 * @property array|null $response_data 响应数据
 * @property Carbon $validated_at 验证时间
 * @property Carbon $created_at 创建时间
 * @property Carbon $updated_at 更新时间
 */
class TicketValidationRecord extends Model
{
    /**
     * 数据表名
     * 
     * @var string
     */
    protected $table = 'cas_ticket_validation_records';
    
    /**
     * 可批量赋值的字段
     * 
     * @var array
     */
    protected $fillable = [
        'ticket',
        'ticket_type',
        'client_name',
        'service_url',
        'user_id',
        'is_success',
        'error_code',
        'error_message',
        'client_ip',
        'user_agent',
        'request_data',
        'response_data',
        'validated_at'
    ];
    
    /**
     * 字段类型转换
     * 
     * @var array
     */
    protected $casts = [
        'is_success' => 'boolean',
        'request_data' => 'array',
        'response_data' => 'array',
        'validated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    /**
     * 票据类型常量
     */
    const TICKET_TYPE_ST = 'ST';  // Service Ticket
    const TICKET_TYPE_PT = 'PT';  // Proxy Ticket
    const TICKET_TYPE_TGT = 'TGT'; // Ticket Granting Ticket
    
    /**
     * 错误代码常量
     */
    const ERROR_INVALID_TICKET = 'INVALID_TICKET';
    const ERROR_EXPIRED_TICKET = 'EXPIRED_TICKET';
    const ERROR_CONSUMED_TICKET = 'CONSUMED_TICKET';
    const ERROR_INVALID_SERVICE = 'INVALID_SERVICE';
    const ERROR_INVALID_CLIENT = 'INVALID_CLIENT';
    const ERROR_UNAUTHORIZED_PROXY = 'UNAUTHORIZED_PROXY';
    const ERROR_NETWORK_FAILURE = 'NETWORK_FAILURE';
    const ERROR_INTERNAL_ERROR = 'INTERNAL_ERROR';
    
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
     * 获取用户信息
     * 
     * @return mixed|null
     */
    public function getUser()
    {
        if (!$this->user_id) {
            return null;
        }
        
        $userModel = config('casserver.user_model', 'App\\Models\\User');
        return $userModel::find($this->user_id);
    }
    
    /**
     * 检查验证是否成功
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->is_success;
    }
    
    /**
     * 检查验证是否失败
     * 
     * @return bool
     */
    public function isFailure(): bool
    {
        return !$this->is_success;
    }
    
    /**
     * 获取错误信息
     * 
     * @return array
     */
    public function getErrorInfo(): array
    {
        return [
            'code' => $this->error_code,
            'message' => $this->error_message
        ];
    }
    
    /**
     * 获取验证耗时（毫秒）
     * 基于created_at和validated_at的差值
     * 
     * @return int
     */
    public function getValidationDuration(): int
    {
        if (!$this->validated_at) {
            return 0;
        }
        
        return $this->created_at->diffInMilliseconds($this->validated_at);
    }
    
    /**
     * 获取客户端位置信息
     * 基于IP地址的地理位置（需要配置IP地理位置服务）
     * 
     * @return array|null
     */
    public function getClientLocation(): ?array
    {
        if (!$this->client_ip) {
            return null;
        }
        
        // 这里可以集成IP地理位置服务
        // 例如：GeoIP2、ipapi.co等
        return [
            'ip' => $this->client_ip,
            'country' => null,
            'city' => null,
            'region' => null
        ];
    }
    
    /**
     * 获取浏览器信息
     * 解析User-Agent字符串
     * 
     * @return array
     */
    public function getBrowserInfo(): array
    {
        if (!$this->user_agent) {
            return [
                'browser' => 'Unknown',
                'version' => 'Unknown',
                'platform' => 'Unknown'
            ];
        }
        
        // 简单的User-Agent解析
        $userAgent = $this->user_agent;
        
        // 检测浏览器
        $browser = 'Unknown';
        if (preg_match('/Chrome\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Chrome';
            $version = $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Firefox';
            $version = $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Safari';
            $version = $matches[1];
        } elseif (preg_match('/Edge\/([0-9.]+)/', $userAgent, $matches)) {
            $browser = 'Edge';
            $version = $matches[1];
        }
        
        // 检测平台
        $platform = 'Unknown';
        if (strpos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $platform = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            $platform = 'iOS';
        }
        
        return [
            'browser' => $browser,
            'version' => $version ?? 'Unknown',
            'platform' => $platform,
            'user_agent' => $userAgent
        ];
    }
    
    /**
     * 记录成功的票据验证
     * 
     * @param string $ticket 票据
     * @param string $ticketType 票据类型
     * @param string $clientName 客户端名称
     * @param string|null $serviceUrl 服务URL
     * @param string|null $userId 用户ID
     * @param array $requestData 请求数据
     * @param array $responseData 响应数据
     * @param string|null $clientIp 客户端IP
     * @param string|null $userAgent 用户代理
     * @return static
     */
    public static function recordSuccess(
        string $ticket,
        string $ticketType,
        string $clientName,
        ?string $serviceUrl = null,
        ?string $userId = null,
        array $requestData = [],
        array $responseData = [],
        ?string $clientIp = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'ticket' => $ticket,
            'ticket_type' => $ticketType,
            'client_name' => $clientName,
            'service_url' => $serviceUrl,
            'user_id' => $userId,
            'is_success' => true,
            'error_code' => null,
            'error_message' => null,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'validated_at' => Carbon::now()
        ]);
    }
    
    /**
     * 记录失败的票据验证
     * 
     * @param string $ticket 票据
     * @param string $ticketType 票据类型
     * @param string $clientName 客户端名称
     * @param string $errorCode 错误代码
     * @param string $errorMessage 错误消息
     * @param string|null $serviceUrl 服务URL
     * @param array $requestData 请求数据
     * @param string|null $clientIp 客户端IP
     * @param string|null $userAgent 用户代理
     * @return static
     */
    public static function recordFailure(
        string $ticket,
        string $ticketType,
        string $clientName,
        string $errorCode,
        string $errorMessage,
        ?string $serviceUrl = null,
        array $requestData = [],
        ?string $clientIp = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'ticket' => $ticket,
            'ticket_type' => $ticketType,
            'client_name' => $clientName,
            'service_url' => $serviceUrl,
            'user_id' => null,
            'is_success' => false,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'request_data' => $requestData,
            'response_data' => [],
            'validated_at' => Carbon::now()
        ]);
    }
    
    /**
     * 获取验证统计信息
     * 
     * @param string|null $clientName 客户端名称
     * @param Carbon|null $startDate 开始日期
     * @param Carbon|null $endDate 结束日期
     * @return array
     */
    public static function getValidationStats(
        ?string $clientName = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): array {
        $query = self::query();
        
        if ($clientName) {
            $query->where('client_name', $clientName);
        }
        
        if ($startDate) {
            $query->where('validated_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('validated_at', '<=', $endDate);
        }
        
        $total = $query->count();
        $successful = $query->where('is_success', true)->count();
        $failed = $total - $successful;
        $successRate = $total > 0 ? round(($successful / $total) * 100, 2) : 0;
        
        // 按票据类型统计
        $byTicketType = $query->selectRaw('ticket_type, COUNT(*) as count, SUM(is_success) as successful')
            ->groupBy('ticket_type')
            ->get()
            ->keyBy('ticket_type')
            ->toArray();
        
        // 按错误代码统计
        $byErrorCode = $query->where('is_success', false)
            ->selectRaw('error_code, COUNT(*) as count')
            ->groupBy('error_code')
            ->orderByDesc('count')
            ->get()
            ->toArray();
        
        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $successRate,
            'by_ticket_type' => $byTicketType,
            'by_error_code' => $byErrorCode
        ];
    }
    
    /**
     * 清理过期的验证记录
     * 
     * @param int $daysToKeep 保留天数
     * @return int 清理的记录数
     */
    public static function cleanOldRecords(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        return self::where('validated_at', '<', $cutoffDate)->delete();
    }
    
    /**
     * 查询作用域：成功的验证
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSuccessful($query)
    {
        return $query->where('is_success', true);
    }
    
    /**
     * 查询作用域：失败的验证
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFailed($query)
    {
        return $query->where('is_success', false);
    }
    
    /**
     * 查询作用域：按票据类型查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ticketType
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTicketType($query, string $ticketType)
    {
        return $query->where('ticket_type', $ticketType);
    }
    
    /**
     * 查询作用域：按客户端查找
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
     * 查询作用域：按用户查找
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
     * 查询作用域：按错误代码查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $errorCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByErrorCode($query, string $errorCode)
    {
        return $query->where('error_code', $errorCode);
    }
    
    /**
     * 查询作用域：按IP地址查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $clientIp
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByClientIp($query, string $clientIp)
    {
        return $query->where('client_ip', $clientIp);
    }
    
    /**
     * 查询作用域：按日期范围查找
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('validated_at', [$startDate, $endDate]);
    }
    
    /**
     * 查询作用域：今天的记录
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeToday($query)
    {
        return $query->whereDate('validated_at', Carbon::today());
    }
    
    /**
     * 查询作用域：最近N天的记录
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecentDays($query, int $days)
    {
        return $query->where('validated_at', '>=', Carbon::now()->subDays($days));
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
        $array['validation_duration'] = $this->getValidationDuration();
        $array['browser_info'] = $this->getBrowserInfo();
        $array['client_location'] = $this->getClientLocation();
        
        // 格式化时间
        $array['validated_at_human'] = $this->validated_at ? $this->validated_at->diffForHumans() : null;
        
        return $array;
    }
}