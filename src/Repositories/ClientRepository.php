<?php

namespace Lyn\LaravelCasServer\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lyn\LaravelCasServer\Models\Client;

/**
 * CAS客户端仓储类
 * 
 * 提供客户端数据访问和管理功能：
 * 1. 客户端查询和验证
 * 2. 客户端状态管理
 * 3. 客户端统计信息
 * 4. 批量操作支持
 */
class ClientRepository
{
    /**
     * 客户端模型实例
     * 
     * @var Client
     */
    protected $client;

    /**
     * 构造函数
     * 
     * @param Client $client 客户端模型
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * 根据客户端名称获取客户端
     * 
     * @param string $name 客户端名称
     * @return Client|null
     */
    public function getClientByName(string $name): ?Client
    {
        return $this->client->where('client_name', $name)->first();
    }

    /**
     * 根据ID获取客户端
     * 
     * @param int $id 客户端ID
     * @return Client|null
     */
    public function getClientById(int $id): ?Client
    {
        return $this->client->find($id);
    }
    
    /**
     * 根据客户端名称获取启用的客户端
     * 
     * @param string $name 客户端名称
     * @return Client|null
     */
    public function getEnabledClientByName(string $name): ?Client
    {
        return $this->client->enabled()->where('client_name', $name)->first();
    }
    
    /**
     * 验证客户端是否存在且启用
     * 
     * @param string $name 客户端名称
     * @return bool
     */
    public function isClientEnabled(string $name): bool
    {
        return $this->client->enabled()->where('client_name', $name)->exists();
    }
    
    /**
     * 根据服务URL查找客户端
     * 
     * @param string $serviceUrl 服务URL
     * @return Client|null
     */
    public function findByServiceUrl(string $serviceUrl): ?Client
    {
        if (empty($serviceUrl)) {
            return null;
        }
        
        // 解析URL获取主机名
        $parsedUrl = parse_url($serviceUrl);
        if (!isset($parsedUrl['host'])) {
            return null;
        }
        
        $host = $parsedUrl['host'];
        
        // 首先尝试精确匹配客户端名称
        $client = $this->getEnabledClientByName($host);
        if ($client) {
            return $client;
        }
        
        // 如果没有找到，尝试匹配重定向URL
        $clients = $this->getAllEnabledClients();
        foreach ($clients as $client) {
            if ($client->isValidRedirectUrl($serviceUrl)) {
                return $client;
            }
        }
        
        return null;
    }
    
    /**
     * 验证客户端重定向URL是否有效
     * 
     * @param string $clientName 客户端名称
     * @param string $redirectUrl 重定向URL
     * @return bool
     */
    public function validateRedirectUrl(string $clientName, string $redirectUrl): bool
    {
        $client = $this->getEnabledClientByName($clientName);
        
        if (!$client) {
            return false;
        }
        
        return $client->isValidRedirectUrl($redirectUrl);
    }
    
    /**
     * 验证客户端密钥
     * 
     * @param string $clientName 客户端名称
     * @param string $secret 客户端密钥
     * @return bool
     */
    public function validateClientSecret(string $clientName, string $secret): bool
    {
        $client = $this->getEnabledClientByName($clientName);
        
        if (!$client) {
            return false;
        }
        
        return $client->validateSecret($secret);
    }
    
    /**
     * 获取所有启用的客户端
     * 
     * @return Collection
     */
    public function getAllEnabledClients(): Collection
    {
        return $this->client->enabled()->get();
    }
    
    /**
     * 分页获取客户端列表
     * 
     * @param int $perPage 每页数量
     * @param array $filters 过滤条件
     * @return LengthAwarePaginator
     */
    public function getPaginatedClients(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->client->newQuery();
        
        // 应用过滤条件
        if (!empty($filters['search'])) {
            $query->searchByName($filters['search']);
        }
        
        if (isset($filters['enabled'])) {
            if ($filters['enabled']) {
                $query->enabled();
            } else {
                $query->where('client_enabled', false);
            }
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
    
    /**
     * 创建新客户端
     * 
     * @param array $data 客户端数据
     * @return Client
     */
    public function createClient(array $data): Client
    {
        // 生成客户端密钥（如果未提供）
        if (empty($data['client_secret'])) {
            $data['client_secret'] = Client::generateSecret();
        }
        
        // 默认启用客户端
        if (!isset($data['client_enabled'])) {
            $data['client_enabled'] = true;
        }
        
        return $this->client->create($data);
    }
    
    /**
     * 更新客户端信息
     * 
     * @param int $id 客户端ID
     * @param array $data 更新数据
     * @return bool
     */
    public function updateClient(int $id, array $data): bool
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return false;
        }
        
        return $client->update($data);
    }
    
    /**
     * 启用客户端
     * 
     * @param int $id 客户端ID
     * @return bool
     */
    public function enableClient(int $id): bool
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return false;
        }
        
        return $client->enable();
    }
    
    /**
     * 禁用客户端
     * 
     * @param int $id 客户端ID
     * @return bool
     */
    public function disableClient(int $id): bool
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return false;
        }
        
        return $client->disable();
    }
    
    /**
     * 删除客户端
     * 
     * @param int $id 客户端ID
     * @return bool
     */
    public function deleteClient(int $id): bool
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return false;
        }
        
        // 清理相关票据
        $client->cleanExpiredTickets();
        
        return $client->delete();
    }
    
    /**
     * 重新生成客户端密钥
     * 
     * @param int $id 客户端ID
     * @return string|null 新密钥
     */
    public function regenerateSecret(int $id): ?string
    {
        $client = $this->getClientById($id);
        
        if (!$client) {
            return null;
        }
        
        $newSecret = Client::generateSecret();
        $client->update(['client_secret' => $newSecret]);
        
        return $newSecret;
    }
    
    /**
     * 获取客户端统计信息
     * 
     * @param string $clientName 客户端名称
     * @param int $days 统计天数
     * @return array
     */
    public function getClientStatistics(string $clientName, int $days = 30): array
    {
        $client = $this->getClientByName($clientName);
        
        if (!$client) {
            return [];
        }
        
        return [
            'active_tickets' => $client->getActiveTicketCount(),
            'validation_stats' => $client->getValidationStatistics($days),
            'total_service_tickets' => $client->serviceTickets()->count(),
            'total_proxy_tickets' => $client->proxyGrantingTickets()->count(),
            'logout_sessions' => $client->logoutSessions()->count()
        ];
    }
    
    /**
     * 批量启用客户端
     * 
     * @param array $ids 客户端ID数组
     * @return int 成功启用的数量
     */
    public function batchEnableClients(array $ids): int
    {
        return $this->client->whereIn('id', $ids)->update(['client_enabled' => true]);
    }
    
    /**
     * 批量禁用客户端
     * 
     * @param array $ids 客户端ID数组
     * @return int 成功禁用的数量
     */
    public function batchDisableClients(array $ids): int
    {
        return $this->client->whereIn('id', $ids)->update(['client_enabled' => false]);
    }
    
    /**
     * 清理所有客户端的过期票据
     * 
     * @return array 清理统计信息
     */
    public function cleanAllExpiredTickets(): array
    {
        $stats = [
            'clients_processed' => 0,
            'tickets_cleaned' => 0
        ];
        
        $clients = $this->getAllEnabledClients();
        
        foreach ($clients as $client) {
            $cleaned = $client->cleanExpiredTickets();
            $stats['clients_processed']++;
            $stats['tickets_cleaned'] += $cleaned;
        }
        
        return $stats;
    }
    
    /**
     * 搜索客户端
     * 
     * @param string $keyword 搜索关键词
     * @param int $limit 限制数量
     * @return Collection
     */
    public function searchClients(string $keyword, int $limit = 10): Collection
    {
        return $this->client->searchByName($keyword)
            ->limit($limit)
            ->get();
    }
    
    /**
     * 检查客户端名称是否已存在
     * 
     * @param string $name 客户端名称
     * @param int|null $excludeId 排除的客户端ID
     * @return bool
     */
    public function isClientNameExists(string $name, ?int $excludeId = null): bool
    {
        $query = $this->client->where('client_name', $name);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        return $query->exists();
    }
    
    /**
     * 获取客户端总数统计
     * 
     * @return array
     */
    public function getClientCounts(): array
    {
        return [
            'total' => $this->client->count(),
            'enabled' => $this->client->enabled()->count(),
            'disabled' => $this->client->where('client_enabled', false)->count()
        ];
    }
}
