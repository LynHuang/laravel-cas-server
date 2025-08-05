<?php

namespace Lyn\LaravelCasServer\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lyn\LaravelCasServer\Events\CasUserLoggedOutEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lyn\LaravelCasServer\Models\LogoutSession;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;

/**
 * CAS用户登出监听器
 * 
 * 处理用户登出事件，负责：
 * 1. 记录用户登出日志
 * 2. 更新登录会话状态
 * 3. 清理用户相关缓存
 * 4. 统计登出数据
 */
class CasUserLoggedOutListener
{
    /**
     * 处理事件
     *
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    public function handle(CasUserLoggedOutEvent $event)
    {
        try {
            // 1. 记录详细的登出日志
            $this->logUserLogout($event);
            
            // 2. 更新登录会话状态
            $this->updateLoginSessions($event);
            
            // 3. 清理用户相关缓存
            $this->cleanupUserCache($event);
            
            // 4. 记录登出统计
            $this->recordLogoutStatistics($event);
            
            // 5. 执行自定义登出钩子
            $this->executeLogoutHooks($event);
            
        } catch (\Exception $e) {
            Log::error('用户登出事件处理失败', [
                'user_id' => $event->getUserId(),
                'session_id' => $event->sessionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 记录用户登出日志
     * 
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    protected function logUserLogout(CasUserLoggedOutEvent $event): void
    {
        $logData = [
            'event' => 'user_logout',
            'user_id' => $event->getUserId(),
            'user_email' => $event->user->email ?? null,
            'user_name' => $event->user->name ?? null,
            'tgt' => $event->tgt ? substr($event->tgt->tgt, 0, 10) . '...' : null,
            'reason' => $event->reason,
            'client_ip' => $event->clientIp,
            'user_agent' => $event->userAgent,
            'session_id' => $event->sessionId,
            'logout_time' => $event->logoutTime->toISOString(),
            'forced' => $event->isForcedLogout(),
            'duration' => $this->calculateSessionDuration($event)
        ];
        
        // 根据登出原因选择不同的日志级别
        if ($event->isForcedLogout()) {
            Log::warning('用户被强制登出', $logData);
        } else {
            Log::info('用户主动登出', $logData);
        }
        
        // 如果配置了审计日志，记录到专门的审计日志中
        if (config('casserver.audit.enabled', false)) {
            Log::channel('audit')->info('CAS_USER_LOGOUT', $logData);
        }
    }
    
    /**
     * 更新登录会话状态
     * 
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    protected function updateLoginSessions(CasUserLoggedOutEvent $event): void
    {
        try {
            $updateData = [
                'is_active' => false,
                'logout_at' => $event->logoutTime,
                'logout_reason' => $event->reason,
                'logout_ip' => $event->clientIp
            ];
            
            // 根据TGT更新会话
            if ($event->tgt) {
                LogoutSession::where('tgt', $event->tgt->tgt)
                    ->where('is_active', true)
                    ->update($updateData);
            }
            
            // 根据用户ID和会话ID更新
            LogoutSession::where('user_id', $event->getUserId())
                ->where('session_id', $event->sessionId)
                ->where('is_active', true)
                ->update($updateData);
                
            Log::debug('登录会话状态已更新', [
                'user_id' => $event->getUserId(),
                'session_id' => $event->sessionId,
                'tgt' => $event->tgt ? substr($event->tgt->tgt, 0, 10) . '...' : null
            ]);
            
        } catch (\Exception $e) {
            Log::warning('更新登录会话状态失败', [
                'user_id' => $event->getUserId(),
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 清理用户相关缓存
     * 
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    protected function cleanupUserCache(CasUserLoggedOutEvent $event): void
    {
        $userId = $event->getUserId();
        $sessionId = $event->sessionId;
        
        // 清理用户相关的缓存键
        $cacheKeys = [
            "cas_user_{$userId}",
            "cas_user_tgt_{$userId}",
            "cas_user_sessions_{$userId}",
            "cas_session_{$sessionId}",
            "cas_user_attributes_{$userId}",
            "cas_user_permissions_{$userId}"
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        
        // 清理带标签的缓存
        Cache::tags(["user_{$userId}", "session_{$sessionId}"])->flush();
        
        Log::debug('用户缓存已清理', [
            'user_id' => $userId,
            'session_id' => $sessionId,
            'cache_keys_count' => count($cacheKeys)
        ]);
    }
    
    /**
     * 记录登出统计
     * 
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    protected function recordLogoutStatistics(CasUserLoggedOutEvent $event): void
    {
        try {
            $today = now()->format('Y-m-d');
            $hour = now()->format('H');
            
            // 增加每日登出统计
            Cache::increment("cas_stats_logout_daily_{$today}");
            
            // 增加每小时登出统计
            Cache::increment("cas_stats_logout_hourly_{$today}_{$hour}");
            
            // 按登出原因统计
            Cache::increment("cas_stats_logout_reason_{$event->reason}_{$today}");
            
            // 如果是强制登出，单独统计
            if ($event->isForcedLogout()) {
                Cache::increment("cas_stats_forced_logout_{$today}");
            }
            
            // 用户登出次数统计
            Cache::increment("cas_stats_user_logout_{$event->getUserId()}_{$today}");
            
        } catch (\Exception $e) {
            Log::debug('登出统计记录失败', [
                'error' => $e->getMessage(),
                'user_id' => $event->getUserId()
            ]);
        }
    }
    
    /**
     * 执行自定义登出钩子
     * 
     * @param CasUserLoggedOutEvent $event
     * @return void
     */
    protected function executeLogoutHooks(CasUserLoggedOutEvent $event): void
    {
        $hooks = config('casserver.logout.hooks', []);
        
        foreach ($hooks as $hook) {
            try {
                if (is_callable($hook)) {
                    call_user_func($hook, $event);
                } elseif (is_string($hook) && class_exists($hook)) {
                    $instance = app($hook);
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($event);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('登出钩子执行失败', [
                    'hook' => is_string($hook) ? $hook : 'callable',
                    'error' => $e->getMessage(),
                    'user_id' => $event->getUserId()
                ]);
            }
        }
    }
    
    /**
     * 计算会话持续时间
     * 
     * @param CasUserLoggedOutEvent $event
     * @return int|null 会话持续时间（秒）
     */
    protected function calculateSessionDuration(CasUserLoggedOutEvent $event): ?int
    {
        if (!$event->tgt) {
            return null;
        }
        
        try {
            $loginTime = $event->tgt->created_at;
            $logoutTime = $event->logoutTime;
            
            return $logoutTime->diffInSeconds($loginTime);
        } catch (\Exception $e) {
            return null;
        }
    }
}