<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Lyn\LaravelCasServer\Models\ProxyGrantingTicket;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;
use Lyn\LaravelCasServer\Repositories\ClientRepository;
use Lyn\LaravelCasServer\Repositories\TicketRepository;

/**
 * CAS管理控制器
 * 
 * 提供CAS服务器的管理功能：
 * 1. 统计信息查看
 * 2. 客户端管理
 * 3. 票据清理
 * 4. 系统监控
 */
class AdminController extends Controller
{
    /**
     * 客户端仓储
     * 
     * @var ClientRepository
     */
    protected $clientRepository;
    
    /**
     * 票据仓储
     * 
     * @var TicketRepository
     */
    protected $ticketRepository;

    /**
     * 构造函数
     * 
     * @param ClientRepository $clientRepository 客户端仓储
     * @param TicketRepository $ticketRepository 票据仓储
     */
    public function __construct(
        ClientRepository $clientRepository,
        TicketRepository $ticketRepository
    ) {
        $this->clientRepository = $clientRepository;
        $this->ticketRepository = $ticketRepository;
    }

    /**
     * 获取CAS服务器统计信息
     * 
     * @param Request $request HTTP请求
     * @return Response JSON格式的统计数据
     */
    public function stats(Request $request)
    {
        try {
            // 缓存统计数据，避免频繁查询
            $stats = Cache::remember('cas_admin_stats', 300, function () {
                return [
                    'tickets' => [
                        'total_st' => ServiceTicket::count(),
                        'active_st' => ServiceTicket::valid()->count(),
                        'expired_st' => ServiceTicket::expired()->count(),
                        'consumed_st' => ServiceTicket::consumed()->count(),
                        
                        'total_tgt' => TicketGrantingTicket::count(),
                        'active_tgt' => TicketGrantingTicket::valid()->count(),
                        'expired_tgt' => TicketGrantingTicket::expired()->count(),
                        
                        'total_pt' => ProxyTicket::count(),
                        'active_pt' => ProxyTicket::valid()->count(),
                        'expired_pt' => ProxyTicket::expired()->count(),
                        
                        'total_pgt' => ProxyGrantingTicket::count(),
                        'active_pgt' => ProxyGrantingTicket::valid()->count(),
                        'expired_pgt' => ProxyGrantingTicket::expired()->count(),
                    ],
                    'clients' => [
                        'total' => Client::count(),
                        'enabled' => Client::enabled()->count(),
                        'disabled' => Client::where('client_enabled', false)->count(),
                    ],
                    'validations' => [
                        'total' => TicketValidationRecord::count(),
                        'successful' => TicketValidationRecord::successful()->count(),
                        'failed' => TicketValidationRecord::failed()->count(),
                        'today' => TicketValidationRecord::whereDate('created_at', today())->count(),
                    ],
                    'system' => [
                        'uptime' => $this->getSystemUptime(),
                        'memory_usage' => $this->getMemoryUsage(),
                        'cache_status' => $this->getCacheStatus(),
                    ]
                ];
            });
            
            // 添加实时数据
            $stats['realtime'] = [
                'current_sessions' => $this->getCurrentSessionCount(),
                'recent_logins' => $this->getRecentLoginCount(),
                'server_time' => now()->toISOString(),
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取CAS统计信息错误', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '获取统计信息时发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 获取客户端列表
     * 
     * @param Request $request HTTP请求
     * @return Response JSON格式的客户端列表
     */
    public function clients(Request $request)
    {
        try {
            $page = $request->get('page', 1);
            $perPage = min($request->get('per_page', 20), 100); // 限制最大每页数量
            $search = $request->get('search', '');
            $status = $request->get('status', 'all'); // all, enabled, disabled
            
            $query = Client::query();
            
            // 搜索过滤
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('client_name', 'like', "%{$search}%")
                      ->orWhere('client_id', 'like', "%{$search}%")
                      ->orWhere('redirect_uri', 'like', "%{$search}%");
                });
            }
            
            // 状态过滤
            if ($status === 'enabled') {
                $query->enabled();
            } elseif ($status === 'disabled') {
                $query->where('client_enabled', false);
            }
            
            $clients = $query->orderBy('created_at', 'desc')
                           ->paginate($perPage, ['*'], 'page', $page);
            
            // 添加统计信息
            $clientsData = $clients->getCollection()->map(function ($client) {
                return [
                    'id' => $client->id,
                    'client_id' => $client->client_id,
                    'client_name' => $client->client_name,
                    'redirect_uri' => $client->redirect_uri,
                    'client_enabled' => $client->client_enabled,
                    'created_at' => $client->created_at,
                    'updated_at' => $client->updated_at,
                    'stats' => [
                        'total_tickets' => $this->getClientTicketCount($client->client_name),
                        'recent_logins' => $this->getClientRecentLoginCount($client->client_name),
                        'last_used' => $this->getClientLastUsed($client->client_name),
                    ]
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => [
                    'clients' => $clientsData,
                    'pagination' => [
                        'current_page' => $clients->currentPage(),
                        'last_page' => $clients->lastPage(),
                        'per_page' => $clients->perPage(),
                        'total' => $clients->total(),
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取客户端列表错误', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '获取客户端列表时发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 切换客户端启用状态
     * 
     * @param Request $request HTTP请求
     * @param Client $client 客户端模型
     * @return Response JSON响应
     */
    public function toggleClient(Request $request, Client $client)
    {
        try {
            $newStatus = !$client->client_enabled;
            $client->update(['client_enabled' => $newStatus]);
            
            Log::info('客户端状态切换', [
                'client_id' => $client->client_id,
                'client_name' => $client->client_name,
                'old_status' => !$newStatus,
                'new_status' => $newStatus,
                'admin_ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => $newStatus ? '客户端已启用' : '客户端已禁用',
                'data' => [
                    'client_enabled' => $newStatus
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('切换客户端状态错误', [
                'error' => $e->getMessage(),
                'client_id' => $client->client_id ?? 'unknown',
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '切换客户端状态时发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 清理过期票据
     * 
     * @param Request $request HTTP请求
     * @return Response JSON响应
     */
    public function cleanExpiredTickets(Request $request)
    {
        try {
            $cleanedCounts = [
                'service_tickets' => ServiceTicket::expired()->delete(),
                'proxy_tickets' => ProxyTicket::expired()->delete(),
                'ticket_granting_tickets' => TicketGrantingTicket::expired()->delete(),
                'proxy_granting_tickets' => ProxyGrantingTicket::expired()->delete(),
            ];
            
            $totalCleaned = array_sum($cleanedCounts);
            
            Log::info('清理过期票据完成', [
                'cleaned_counts' => $cleanedCounts,
                'total_cleaned' => $totalCleaned,
                'admin_ip' => $request->ip()
            ]);
            
            // 清除统计缓存
            Cache::forget('cas_admin_stats');
            
            return response()->json([
                'success' => true,
                'message' => "成功清理 {$totalCleaned} 个过期票据",
                'data' => [
                    'cleaned_counts' => $cleanedCounts,
                    'total_cleaned' => $totalCleaned
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('清理过期票据错误', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '清理过期票据时发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 获取系统运行时间
     * 
     * @return string
     */
    protected function getSystemUptime(): string
    {
        if (function_exists('sys_getloadavg') && PHP_OS_FAMILY !== 'Windows') {
            $uptime = shell_exec('uptime');
            return trim($uptime ?: 'Unknown');
        }
        
        return 'N/A (Windows or restricted environment)';
    }

    /**
     * 获取内存使用情况
     * 
     * @return array
     */
    protected function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'formatted' => [
                'current' => $this->formatBytes(memory_get_usage(true)),
                'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            ]
        ];
    }

    /**
     * 获取缓存状态
     * 
     * @return array
     */
    protected function getCacheStatus(): array
    {
        try {
            $testKey = 'cas_cache_test_' . time();
            Cache::put($testKey, 'test', 60);
            $canWrite = Cache::get($testKey) === 'test';
            Cache::forget($testKey);
            
            return [
                'driver' => config('cache.default'),
                'status' => $canWrite ? 'working' : 'error',
                'can_read' => true,
                'can_write' => $canWrite,
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 获取当前会话数量
     * 
     * @return int
     */
    protected function getCurrentSessionCount(): int
    {
        return TicketGrantingTicket::valid()->count();
    }

    /**
     * 获取最近登录数量
     * 
     * @return int
     */
    protected function getRecentLoginCount(): int
    {
        return TicketValidationRecord::where('created_at', '>=', now()->subHour())
                                   ->successful()
                                   ->count();
    }

    /**
     * 获取客户端票据数量
     * 
     * @param string $clientName
     * @return int
     */
    protected function getClientTicketCount(string $clientName): int
    {
        return ServiceTicket::where('client_name', $clientName)->count();
    }

    /**
     * 获取客户端最近登录数量
     * 
     * @param string $clientName
     * @return int
     */
    protected function getClientRecentLoginCount(string $clientName): int
    {
        return TicketValidationRecord::where('client_name', $clientName)
                                   ->where('created_at', '>=', now()->subDay())
                                   ->successful()
                                   ->count();
    }

    /**
     * 获取客户端最后使用时间
     * 
     * @param string $clientName
     * @return string|null
     */
    protected function getClientLastUsed(string $clientName): ?string
    {
        $lastRecord = TicketValidationRecord::where('client_name', $clientName)
                                          ->latest()
                                          ->first();
        
        return $lastRecord ? $lastRecord->created_at->toISOString() : null;
    }

    /**
     * 格式化字节数
     * 
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}