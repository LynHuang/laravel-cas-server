<?php

namespace Lyn\LaravelCasServer\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;

/**
 * CAS用户登出事件
 * 
 * 当用户主动登出或被强制登出时触发此事件，
 * 用于记录登出行为和清理相关资源
 */
class CasUserLoggedOutEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * 用户对象
     * 
     * @var User
     */
    public $user;
    
    /**
     * TGT票据
     * 
     * @var TicketGrantingTicket|null
     */
    public $tgt;
    
    /**
     * 登出原因
     * 
     * @var string
     */
    public $reason;
    
    /**
     * 客户端IP地址
     * 
     * @var string
     */
    public $clientIp;
    
    /**
     * 用户代理
     * 
     * @var string
     */
    public $userAgent;
    
    /**
     * 会话ID
     * 
     * @var string
     */
    public $sessionId;
    
    /**
     * 登出时间
     * 
     * @var \Carbon\Carbon
     */
    public $logoutTime;

    /**
     * 创建新的事件实例
     * 
     * @param User $user 用户对象
     * @param TicketGrantingTicket|null $tgt TGT票据
     * @param string $reason 登出原因
     * @param string $clientIp 客户端IP
     * @param string $userAgent 用户代理
     * @param string $sessionId 会话ID
     */
    public function __construct(
        User $user,
        ?TicketGrantingTicket $tgt = null,
        string $reason = 'user_logout',
        string $clientIp = '',
        string $userAgent = '',
        string $sessionId = ''
    ) {
        $this->user = $user;
        $this->tgt = $tgt;
        $this->reason = $reason;
        $this->clientIp = $clientIp;
        $this->userAgent = $userAgent;
        $this->sessionId = $sessionId;
        $this->logoutTime = now();
    }

    /**
     * 获取广播频道
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('cas-user-logout');
    }
    
    /**
     * 获取用户ID
     * 
     * @return int
     */
    public function getUserId(): int
    {
        return $this->user->id;
    }
    
    /**
     * 是否为强制登出
     * 
     * @return bool
     */
    public function isForcedLogout(): bool
    {
        return in_array($this->reason, ['admin_logout', 'session_timeout', 'security_logout']);
    }
    
    /**
     * 转换为数组
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_email' => $this->user->email ?? null,
            'tgt' => $this->tgt ? $this->tgt->tgt : null,
            'reason' => $this->reason,
            'client_ip' => $this->clientIp,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'logout_time' => $this->logoutTime->toISOString(),
            'forced' => $this->isForcedLogout()
        ];
    }
}