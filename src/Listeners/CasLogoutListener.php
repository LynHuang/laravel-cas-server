<?php

namespace Lyn\LaravelCasServer\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lyn\LaravelCasServer\Events\CasLogoutEvent;
use Lyn\LaravelCasServer\Repositories\TokenRepository;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * CAS单点登出监听器
 * 
 * 处理CAS单点登出事件，负责：
 * 1. 通知所有相关客户端应用执行登出
 * 2. 清理相关的票据和会话
 * 3. 记录登出日志
 * 4. 处理登出失败的重试机制
 */
class CasLogoutListener implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * Token仓储
     * 
     * @var TokenRepository
     */
    protected $tokenRepository;
    
    /**
     * 最大重试次数
     * 
     * @var int
     */
    public $tries = 3;
    
    /**
     * 超时时间（秒）
     * 
     * @var int
     */
    public $timeout = 60;

    /**
     * 创建事件监听器
     *
     * @param TokenRepository $tokenRepository
     */
    public function __construct(TokenRepository $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * 处理事件
     *
     * @param CasLogoutEvent $event
     * @return void
     */
    public function handle(CasLogoutEvent $event)
    {
        $sessionId = $event->getSessionId();
        $user = $event->getUser();
        $tgt = $event->getTgt();
        
        Log::info('开始处理CAS单点登出', [
            'session_id' => $sessionId,
            'user_id' => $event->getUserId(),
            'reason' => $event->getReason(),
            'client_ip' => $event->getClientIp()
        ]);
        
        try {
            // 1. 获取与此会话相关的所有客户端token
            $clientTokens = $this->getClientTokens($sessionId, $tgt);
            
            // 2. 向所有客户端发送登出通知
            $this->notifyClients($clientTokens, $event);
            
            // 3. 清理相关票据
            $this->cleanupTickets($sessionId, $tgt);
            
            // 4. 清理会话缓存
            $this->cleanupSessionCache($sessionId);
            
            // 5. 记录登出成功日志
            $this->logLogoutSuccess($event, count($clientTokens));
            
        } catch (\Exception $e) {
            Log::error('CAS单点登出处理失败', [
                'session_id' => $sessionId,
                'user_id' => $event->getUserId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 重新抛出异常以触发队列重试
            throw $e;
        }
    }
    
    /**
     * 获取客户端token信息
     * 
     * @param string $sessionId
     * @param TicketGrantingTicket|null $tgt
     * @return array
     */
    protected function getClientTokens(string $sessionId, ?TicketGrantingTicket $tgt): array
    {
        $tokens = [];
        
        // 从TokenRepository获取token（如果存在）
        if (method_exists($this->tokenRepository, 'getTokensBySessionId')) {
            $repoTokens = $this->tokenRepository->getTokensBySessionId($sessionId);
            foreach ($repoTokens as $token) {
                $tokens[] = [
                    'client_id' => $token->client_id ?? null,
                    'logout_url' => $token->logout_url ?? null,
                    'session_id' => $sessionId,
                    'ticket' => $token->ticket ?? null,
                    'service_url' => $token->service_url ?? null
                ];
            }
        }
        
        // 从ServiceTicket表获取相关的客户端信息
        if ($tgt) {
            $serviceTickets = ServiceTicket::where('tgt_id', $tgt->id)
                ->where('consumed', true)
                ->with('client')
                ->get();
                
            foreach ($serviceTickets as $st) {
                if ($st->client && $st->client->logout_url) {
                    $tokens[] = [
                        'client_id' => $st->client->id,
                        'logout_url' => $st->client->logout_url,
                        'session_id' => $sessionId,
                        'ticket' => $st->st,
                        'service_url' => $st->service_url
                    ];
                }
            }
        }
        
        // 去重
        $uniqueTokens = [];
        foreach ($tokens as $token) {
            $key = $token['client_id'] . '_' . $token['logout_url'];
            $uniqueTokens[$key] = $token;
        }
        
        return array_values($uniqueTokens);
    }
    
    /**
     * 通知所有客户端
     * 
     * @param array $clientTokens
     * @param CasLogoutEvent $event
     * @return void
     */
    protected function notifyClients(array $clientTokens, CasLogoutEvent $event): void
    {
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($clientTokens as $token) {
            try {
                $this->sendLogoutRequest($token, $event);
                $successCount++;
            } catch (\Exception $e) {
                $failureCount++;
                Log::warning('客户端登出通知失败', [
                    'client_id' => $token['client_id'],
                    'logout_url' => $token['logout_url'],
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        Log::info('客户端登出通知完成', [
            'session_id' => $event->getSessionId(),
            'total_clients' => count($clientTokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]);
    }
    
    /**
     * 向客户端发送登出请求
     * 
     * @param array $token
     * @param CasLogoutEvent $event
     * @return void
     * @throws \Exception
     */
    protected function sendLogoutRequest(array $token, CasLogoutEvent $event): void
    {
        if (empty($token['logout_url'])) {
            Log::debug('客户端未配置登出URL，跳过通知', [
                'client_id' => $token['client_id']
            ]);
            return;
        }
        
        $logoutRequest = $this->buildLogoutRequest($token, $event);
        
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(config('casserver.logout_timeout', 10))
                ->retry(2, 1000)
                ->post($token['logout_url'], [
                    'logoutRequest' => $logoutRequest,
                    'service' => $token['service_url'] ?? ''
                ]);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response->successful()) {
                Log::info('CAS登出请求发送成功', [
                    'client_id' => $token['client_id'],
                    'logout_url' => $token['logout_url'],
                    'status' => $response->status(),
                    'duration_ms' => $duration
                ]);
            } else {
                throw new \Exception("HTTP {$response->status()}: {$response->body()}");
            }
            
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::error('CAS登出请求发送失败', [
                'client_id' => $token['client_id'],
                'logout_url' => $token['logout_url'],
                'error' => $e->getMessage(),
                'duration_ms' => $duration
            ]);
            
            throw $e;
        }
    }
    
    /**
     * 构建登出请求
     * 
     * @param array $token
     * @param CasLogoutEvent $event
     * @return string
     */
    protected function buildLogoutRequest(array $token, CasLogoutEvent $event): string
    {
        $requestId = 'LR-' . uniqid();
        $issueInstant = $event->getLogoutTime()->toISOString();
        $sessionIndex = $token['session_id'];
        $nameId = $event->getUserId() ?? '@NOT_USED@';
        
        // 构建SAML 2.0格式的登出请求
        $logoutRequest = '<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ' .
                        'xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ' .
                        'ID="' . $requestId . '" ' .
                        'Version="2.0" ' .
                        'IssueInstant="' . $issueInstant . '">' .
                        '<saml:Issuer>' . config('casserver.server_name', 'CAS Server') . '</saml:Issuer>' .
                        '<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">' . $nameId . '</saml:NameID>' .
                        '<samlp:SessionIndex>' . $sessionIndex . '</samlp:SessionIndex>' .
                        '</samlp:LogoutRequest>';
        
        return $logoutRequest;
    }
    
    /**
     * 清理相关票据
     * 
     * @param string $sessionId
     * @param TicketGrantingTicket|null $tgt
     * @return void
     */
    protected function cleanupTickets(string $sessionId, ?TicketGrantingTicket $tgt): void
    {
        DB::transaction(function () use ($sessionId, $tgt) {
            if ($tgt) {
                // 删除相关的Service Tickets
                ServiceTicket::where('tgt_id', $tgt->id)->delete();
                
                // 删除相关的Proxy Tickets
                ProxyTicket::where('tgt_id', $tgt->id)->delete();
                
                // 删除TGT
                $tgt->delete();
                
                Log::debug('已清理TGT相关票据', [
                    'tgt' => $tgt->tgt,
                    'session_id' => $sessionId
                ]);
            }
            
            // 清理其他可能的会话相关数据
            $this->cleanupSessionRelatedData($sessionId);
        });
    }
    
    /**
     * 清理会话相关数据
     * 
     * @param string $sessionId
     * @return void
     */
    protected function cleanupSessionRelatedData(string $sessionId): void
    {
        // 清理可能存在的会话相关缓存
        $cacheKeys = [
            "cas_session_{$sessionId}",
            "cas_user_session_{$sessionId}",
            "cas_tgt_session_{$sessionId}"
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
    
    /**
     * 清理会话缓存
     * 
     * @param string $sessionId
     * @return void
     */
    protected function cleanupSessionCache(string $sessionId): void
    {
        // 清理Laravel会话存储
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('id', $sessionId)
                ->delete();
        }
        
        // 清理其他可能的缓存
        Cache::tags(['cas_sessions'])->flush();
    }
    
    /**
     * 记录登出成功日志
     * 
     * @param CasLogoutEvent $event
     * @param int $clientCount
     * @return void
     */
    protected function logLogoutSuccess(CasLogoutEvent $event, int $clientCount): void
    {
        Log::info('CAS单点登出处理完成', [
            'session_id' => $event->getSessionId(),
            'user_id' => $event->getUserId(),
            'reason' => $event->getReason(),
            'client_count' => $clientCount,
            'client_ip' => $event->getClientIp(),
            'user_agent' => $event->getUserAgent(),
            'logout_time' => $event->getLogoutTime()->toISOString(),
            'forced' => $event->isForcedLogout()
        ]);
    }
    
    /**
     * 处理失败的任务
     * 
     * @param CasLogoutEvent $event
     * @param \Exception $exception
     * @return void
     */
    public function failed(CasLogoutEvent $event, \Exception $exception): void
    {
        Log::error('CAS单点登出处理最终失败', [
            'session_id' => $event->getSessionId(),
            'user_id' => $event->getUserId(),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
