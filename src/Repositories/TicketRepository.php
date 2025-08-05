<?php

namespace Lyn\LaravelCasServer\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Lyn\LaravelCasServer\Models\Ticket;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Lyn\LaravelCasServer\Models\ProxyGrantingTicket;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;

/**
 * CAS票据仓储类
 * 
 * 提供票据数据访问和管理功能：
 * 1. 各类票据的查询和验证
 * 2. 票据生命周期管理
 * 3. 票据统计和清理
 * 4. 验证记录管理
 */
class TicketRepository
{
    /**
     * 基础票据模型
     * 
     * @var Ticket
     */
    protected $ticket;
    
    /**
     * 服务票据模型
     * 
     * @var ServiceTicket
     */
    protected $serviceTicket;
    
    /**
     * 代理票据模型
     * 
     * @var ProxyTicket
     */
    protected $proxyTicket;
    
    /**
     * 票据授权票据模型
     * 
     * @var TicketGrantingTicket
     */
    protected $ticketGrantingTicket;
    
    /**
     * 代理授权票据模型
     * 
     * @var ProxyGrantingTicket
     */
    protected $proxyGrantingTicket;
    
    /**
     * 票据验证记录模型
     * 
     * @var TicketValidationRecord
     */
    protected $validationRecord;

    /**
     * 构造函数
     * 
     * @param Ticket $ticket 基础票据模型
     * @param ServiceTicket $serviceTicket 服务票据模型
     * @param ProxyTicket $proxyTicket 代理票据模型
     * @param TicketGrantingTicket $ticketGrantingTicket 票据授权票据模型
     * @param ProxyGrantingTicket $proxyGrantingTicket 代理授权票据模型
     * @param TicketValidationRecord $validationRecord 验证记录模型
     */
    public function __construct(
        Ticket $ticket,
        ServiceTicket $serviceTicket,
        ProxyTicket $proxyTicket,
        TicketGrantingTicket $ticketGrantingTicket,
        ProxyGrantingTicket $proxyGrantingTicket,
        TicketValidationRecord $validationRecord
    ) {
        $this->ticket = $ticket;
        $this->serviceTicket = $serviceTicket;
        $this->proxyTicket = $proxyTicket;
        $this->ticketGrantingTicket = $ticketGrantingTicket;
        $this->proxyGrantingTicket = $proxyGrantingTicket;
        $this->validationRecord = $validationRecord;
    }

    // ==================== 服务票据(ST)管理 ====================
    
    /**
     * 根据ST字符串获取服务票据
     * 
     * @param string $st 服务票据字符串
     * @return ServiceTicket|null
     */
    public function getServiceTicketBySt(string $st): ?ServiceTicket
    {
        return $this->serviceTicket->where('st', $st)->first();
    }
    
    /**
     * 获取有效的服务票据
     * 
     * @param string $st 服务票据字符串
     * @return ServiceTicket|null
     */
    public function getValidServiceTicket(string $st): ?ServiceTicket
    {
        return $this->serviceTicket->valid()->where('st', $st)->first();
    }
    
    /**
     * 创建服务票据
     * 
     * @param array $data 票据数据
     * @return ServiceTicket
     */
    public function createServiceTicket(array $data): ServiceTicket
    {
        return $this->serviceTicket->create($data);
    }
    
    /**
     * 获取客户端的服务票据列表
     * 
     * @param string $clientName 客户端名称
     * @param int $limit 限制数量
     * @return Collection
     */
    public function getServiceTicketsByClient(string $clientName, int $limit = 50): Collection
    {
        return $this->serviceTicket->forClient($clientName)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * 获取TGT关联的服务票据
     * 
     * @param string $tgt TGT字符串
     * @return Collection
     */
    public function getServiceTicketsByTgt(string $tgt): Collection
    {
        return $this->serviceTicket->forTgt($tgt)->get();
    }

    // ==================== 代理票据(PT)管理 ====================
    
    /**
     * 根据PT字符串获取代理票据
     * 
     * @param string $pt 代理票据字符串
     * @return ProxyTicket|null
     */
    public function getProxyTicketByPt(string $pt): ?ProxyTicket
    {
        return $this->proxyTicket->where('pt', $pt)->first();
    }
    
    /**
     * 获取有效的代理票据
     * 
     * @param string $pt 代理票据字符串
     * @return ProxyTicket|null
     */
    public function getValidProxyTicket(string $pt): ?ProxyTicket
    {
        return $this->proxyTicket->valid()->where('pt', $pt)->first();
    }
    
    /**
     * 创建代理票据
     * 
     * @param array $data 票据数据
     * @return ProxyTicket
     */
    public function createProxyTicket(array $data): ProxyTicket
    {
        return $this->proxyTicket->create($data);
    }
    
    /**
     * 获取PGT关联的代理票据
     * 
     * @param string $pgt PGT字符串
     * @return Collection
     */
    public function getProxyTicketsByPgt(string $pgt): Collection
    {
        return $this->proxyTicket->forPgt($pgt)->get();
    }

    // ==================== 票据授权票据(TGT)管理 ====================
    
    /**
     * 根据TGT字符串获取票据授权票据
     * 
     * @param string $tgt TGT字符串
     * @return TicketGrantingTicket|null
     */
    public function getTicketGrantingTicketByTgt(string $tgt): ?TicketGrantingTicket
    {
        return $this->ticketGrantingTicket->where('tgt', $tgt)->first();
    }
    
    /**
     * 获取有效的TGT
     * 
     * @param string $tgt TGT字符串
     * @return TicketGrantingTicket|null
     */
    public function getValidTicketGrantingTicket(string $tgt): ?TicketGrantingTicket
    {
        return $this->ticketGrantingTicket->valid()->where('tgt', $tgt)->first();
    }
    
    /**
     * 创建票据授权票据
     * 
     * @param array $data 票据数据
     * @return TicketGrantingTicket
     */
    public function createTicketGrantingTicket(array $data): TicketGrantingTicket
    {
        return $this->ticketGrantingTicket->create($data);
    }
    
    /**
     * 获取用户的TGT列表
     * 
     * @param int $userId 用户ID
     * @return Collection
     */
    public function getTicketGrantingTicketsByUser(int $userId): Collection
    {
        return $this->ticketGrantingTicket->forUser($userId)->get();
    }
    
    /**
     * 获取用户的有效TGT
     * 
     * @param int $userId 用户ID
     * @return TicketGrantingTicket|null
     */
    public function getValidTicketGrantingTicketByUser(int $userId): ?TicketGrantingTicket
    {
        return $this->ticketGrantingTicket->valid()->forUser($userId)->first();
    }

    // ==================== 代理授权票据(PGT)管理 ====================
    
    /**
     * 根据PGT字符串获取代理授权票据
     * 
     * @param string $pgt PGT字符串
     * @return ProxyGrantingTicket|null
     */
    public function getProxyGrantingTicketByPgt(string $pgt): ?ProxyGrantingTicket
    {
        return $this->proxyGrantingTicket->where('pgt', $pgt)->first();
    }
    
    /**
     * 根据PGT IOU获取代理授权票据
     * 
     * @param string $pgtIou PGT IOU字符串
     * @return ProxyGrantingTicket|null
     */
    public function getProxyGrantingTicketByPgtIou(string $pgtIou): ?ProxyGrantingTicket
    {
        return $this->proxyGrantingTicket->where('pgt_iou', $pgtIou)->first();
    }
    
    /**
     * 获取有效的PGT
     * 
     * @param string $pgt PGT字符串
     * @return ProxyGrantingTicket|null
     */
    public function getValidProxyGrantingTicket(string $pgt): ?ProxyGrantingTicket
    {
        return $this->proxyGrantingTicket->valid()->where('pgt', $pgt)->first();
    }
    
    /**
     * 创建代理授权票据
     * 
     * @param array $data 票据数据
     * @return ProxyGrantingTicket
     */
    public function createProxyGrantingTicket(array $data): ProxyGrantingTicket
    {
        return $this->proxyGrantingTicket->create($data);
    }
    
    /**
     * 获取ST关联的PGT
     * 
     * @param string $st ST字符串
     * @return Collection
     */
    public function getProxyGrantingTicketsBySt(string $st): Collection
    {
        return $this->proxyGrantingTicket->forSt($st)->get();
    }

    // ==================== 票据清理和统计 ====================
    
    /**
     * 清理过期的服务票据
     * 
     * @return int 清理数量
     */
    public function cleanExpiredServiceTickets(): int
    {
        return $this->serviceTicket->expired()->delete();
    }
    
    /**
     * 清理过期的代理票据
     * 
     * @return int 清理数量
     */
    public function cleanExpiredProxyTickets(): int
    {
        return $this->proxyTicket->expired()->delete();
    }
    
    /**
     * 清理过期的TGT
     * 
     * @return int 清理数量
     */
    public function cleanExpiredTicketGrantingTickets(): int
    {
        return $this->ticketGrantingTicket->expired()->delete();
    }
    
    /**
     * 清理过期的PGT
     * 
     * @return int 清理数量
     */
    public function cleanExpiredProxyGrantingTickets(): int
    {
        return $this->proxyGrantingTicket->expired()->delete();
    }
    
    /**
     * 清理所有过期票据
     * 
     * @return array 清理统计
     */
    public function cleanAllExpiredTickets(): array
    {
        return [
            'service_tickets' => $this->cleanExpiredServiceTickets(),
            'proxy_tickets' => $this->cleanExpiredProxyTickets(),
            'ticket_granting_tickets' => $this->cleanExpiredTicketGrantingTickets(),
            'proxy_granting_tickets' => $this->cleanExpiredProxyGrantingTickets()
        ];
    }
    
    /**
     * 获取票据统计信息
     * 
     * @return array
     */
    public function getTicketStatistics(): array
    {
        return [
            'service_tickets' => [
                'total' => $this->serviceTicket->count(),
                'valid' => $this->serviceTicket->valid()->count(),
                'expired' => $this->serviceTicket->expired()->count()
            ],
            'proxy_tickets' => [
                'total' => $this->proxyTicket->count(),
                'valid' => $this->proxyTicket->valid()->count(),
                'expired' => $this->proxyTicket->expired()->count()
            ],
            'ticket_granting_tickets' => [
                'total' => $this->ticketGrantingTicket->count(),
                'valid' => $this->ticketGrantingTicket->valid()->count(),
                'expired' => $this->ticketGrantingTicket->expired()->count()
            ],
            'proxy_granting_tickets' => [
                'total' => $this->proxyGrantingTicket->count(),
                'valid' => $this->proxyGrantingTicket->valid()->count(),
                'expired' => $this->proxyGrantingTicket->expired()->count()
            ]
        ];
    }
    
    /**
     * 获取用户的活跃票据统计
     * 
     * @param int $userId 用户ID
     * @return array
     */
    public function getUserActiveTicketStatistics(int $userId): array
    {
        return [
            'ticket_granting_tickets' => $this->ticketGrantingTicket->valid()->forUser($userId)->count(),
            'service_tickets' => $this->serviceTicket->valid()->whereHas('ticketGrantingTicket', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })->count(),
            'proxy_tickets' => $this->proxyTicket->valid()->whereHas('proxyGrantingTicket.serviceTicket.ticketGrantingTicket', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })->count()
        ];
    }

    // ==================== 验证记录管理 ====================
    
    /**
     * 创建验证记录
     * 
     * @param array $data 验证记录数据
     * @return TicketValidationRecord
     */
    public function createValidationRecord(array $data): TicketValidationRecord
    {
        return $this->validationRecord->create($data);
    }
    
    /**
     * 获取验证记录列表
     * 
     * @param array $filters 过滤条件
     * @param int $perPage 每页数量
     * @return LengthAwarePaginator
     */
    public function getValidationRecords(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->validationRecord->newQuery();
        
        // 应用过滤条件
        if (!empty($filters['client_name'])) {
            $query->forClient($filters['client_name']);
        }
        
        if (!empty($filters['user_id'])) {
            $query->forUser($filters['user_id']);
        }
        
        if (!empty($filters['ticket_type'])) {
            $query->forTicketType($filters['ticket_type']);
        }
        
        if (!empty($filters['success'])) {
            if ($filters['success']) {
                $query->successful();
            } else {
                $query->failed();
            }
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('validated_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('validated_at', '<=', $filters['date_to']);
        }
        
        return $query->orderBy('validated_at', 'desc')->paginate($perPage);
    }
    
    /**
     * 获取验证统计信息
     * 
     * @param array $filters 过滤条件
     * @return array
     */
    public function getValidationStatistics(array $filters = []): array
    {
        $query = $this->validationRecord->newQuery();
        
        // 应用过滤条件
        if (!empty($filters['client_name'])) {
            $query->forClient($filters['client_name']);
        }
        
        if (!empty($filters['date_from'])) {
            $query->where('validated_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('validated_at', '<=', $filters['date_to']);
        }
        
        $total = $query->count();
        $successful = $query->clone()->successful()->count();
        $failed = $query->clone()->failed()->count();
        
        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $failed,
            'success_rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
        ];
    }
    
    /**
     * 清理旧的验证记录
     * 
     * @param int $days 保留天数
     * @return int 清理数量
     */
    public function cleanOldValidationRecords(int $days = 90): int
    {
        $cutoffDate = Carbon::now()->subDays($days);
        return $this->validationRecord->where('validated_at', '<', $cutoffDate)->delete();
    }

    // ==================== 通用查询方法 ====================
    
    /**
     * 根据票据字符串查找任意类型的票据
     * 
     * @param string $ticketString 票据字符串
     * @return array|null [type, ticket]
     */
    public function findTicketByString(string $ticketString): ?array
    {
        // 根据前缀判断票据类型
        if (strpos($ticketString, 'ST-') === 0) {
            $ticket = $this->getServiceTicketBySt($ticketString);
            return $ticket ? ['type' => 'ST', 'ticket' => $ticket] : null;
        }
        
        if (strpos($ticketString, 'PT-') === 0) {
            $ticket = $this->getProxyTicketByPt($ticketString);
            return $ticket ? ['type' => 'PT', 'ticket' => $ticket] : null;
        }
        
        if (strpos($ticketString, 'TGT-') === 0) {
            $ticket = $this->getTicketGrantingTicketByTgt($ticketString);
            return $ticket ? ['type' => 'TGT', 'ticket' => $ticket] : null;
        }
        
        if (strpos($ticketString, 'PGT-') === 0) {
            $ticket = $this->getProxyGrantingTicketByPgt($ticketString);
            return $ticket ? ['type' => 'PGT', 'ticket' => $ticket] : null;
        }
        
        return null;
    }
    
    /**
     * 批量删除用户的所有票据
     * 
     * @param int $userId 用户ID
     * @return array 删除统计
     */
    public function deleteAllUserTickets(int $userId): array
    {
        $stats = [
            'ticket_granting_tickets' => 0,
            'service_tickets' => 0,
            'proxy_granting_tickets' => 0,
            'proxy_tickets' => 0
        ];
        
        // 删除TGT（会级联删除相关票据）
        $tgts = $this->ticketGrantingTicket->forUser($userId)->get();
        foreach ($tgts as $tgt) {
            $stats['service_tickets'] += $tgt->serviceTickets()->count();
            $stats['proxy_granting_tickets'] += $tgt->serviceTickets()->withCount('proxyGrantingTickets')->get()->sum('proxy_granting_tickets_count');
            $tgt->delete();
            $stats['ticket_granting_tickets']++;
        }
        
        return $stats;
    }
}