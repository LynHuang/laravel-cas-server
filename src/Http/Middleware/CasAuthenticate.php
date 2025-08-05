<?php

namespace Lyn\LaravelCasServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\LogoutSession;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;
use Lyn\LaravelCasServer\Repositories\ClientRepository;
use Lyn\LaravelCasServer\Services\TicketManger;
use Lyn\LaravelCasServer\Services\AuthService;

/**
 * CAS认证中间件
 * 
 * 处理CAS单点登录的核心逻辑：
 * 1. 验证客户端服务的合法性
 * 2. 检查用户登录状态
 * 3. 生成和管理TGT（票据授权票据）
 * 4. 生成ST（服务票据）并重定向到客户端
 * 5. 处理单点登录会话管理
 * 6. 记录认证日志和审计信息
 */
class CasAuthenticate
{
    /**
     * 用户登录交互接口
     * 
     * @var UserLogin
     */
    protected $loginInteraction;
    
    /**
     * 客户端仓储
     * 
     * @var ClientRepository
     */
    protected $clientRepository;
    
    /**
     * 票据管理服务
     * 
     * @var TicketManger
     */
    protected $ticketManager;
    
    /**
     * 认证服务
     * 
     * @var AuthService
     */
    protected $authService;

    /**
     * 构造函数
     * 
     * @param UserLogin $loginInteraction 用户登录交互
     * @param ClientRepository $clientRepository 客户端仓储
     * @param TicketManger $ticketManager 票据管理器
     * @param AuthService $authService 认证服务
     */
    public function __construct(
        UserLogin $loginInteraction,
        ClientRepository $clientRepository,
        TicketManger $ticketManager,
        AuthService $authService
    ) {
        $this->loginInteraction = $loginInteraction;
        $this->clientRepository = $clientRepository;
        $this->ticketManager = $ticketManager;
        $this->authService = $authService;
    }

    /**
     * 处理传入的请求
     * 
     * 实现CAS协议的认证流程：
     * 1. 解析并验证service参数
     * 2. 验证客户端服务的合法性
     * 3. 检查用户是否已登录
     * 4. 如果已登录，生成ST并重定向
     * 5. 如果未登录，继续到登录页面
     *
     * @param Request $request HTTP请求
     * @param Closure $next 下一个中间件
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // 获取service参数（客户端服务URL）
            $serviceUrl = $request->get('service', '');
            
            // 如果没有service参数，继续处理（可能是访问CAS服务器本身）
            if (empty($serviceUrl)) {
                return $next($request);
            }
            
            // 验证并获取客户端信息
            $client = $this->validateAndGetClient($serviceUrl);
            if (!$client) {
                return $this->handleInvalidClient($serviceUrl);
            }
            
            // 检查用户登录状态
            $user = Auth::user();
            if (!$user) {
                // 用户未登录，继续到登录页面
                // 将service参数传递给登录页面，登录成功后重定向回来
                $request->session()->put('cas_service', $serviceUrl);
                return $next($request);
            }
            
            // 用户已登录，处理单点登录逻辑
            return $this->handleAuthenticatedUser($request, $user, $client, $serviceUrl);
            
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('CAS认证中间件错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            // 返回错误页面
            return $this->loginInteraction->showErrorPage(
                new CasException(CasException::INTERNAL_ERROR, '认证过程中发生错误')
            );
        }
    }
    
    /**
     * 验证并获取客户端信息
     * 
     * @param string $serviceUrl 服务URL
     * @return Client|null
     */
    protected function validateAndGetClient(string $serviceUrl): ?Client
    {
        // 使用ClientRepository的findByServiceUrl方法查找客户端
        $client = $this->clientRepository->findByServiceUrl($serviceUrl);
        
        if (!$client || !$client->isEnabled()) {
            return null;
        }
        
        return $client;
    }
    
    /**
     * 从service URL中提取客户端名称
     * 
     * @param string $serviceUrl
     * @return string|null
     */
    protected function extractClientNameFromService(string $serviceUrl): ?string
    {
        // 解析URL
        $parsedUrl = parse_url($serviceUrl);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return null;
        }
        
        // 可以根据域名、路径等规则提取客户端名称
        // 这里简化处理，使用域名作为客户端名称
        return $parsedUrl['host'];
    }
    
    /**
     * 验证service URL是否匹配客户端配置
     * 
     * @param string $serviceUrl
     * @param Client $client
     * @return bool
     */
    protected function validateServiceUrl(string $serviceUrl, Client $client): bool
    {
        // 检查service URL是否与客户端的重定向URL匹配
        $clientRedirect = $client->client_redirect;
        
        // 简单的前缀匹配
        if (strpos($serviceUrl, $clientRedirect) === 0) {
            return true;
        }
        
        // 可以添加更复杂的匹配规则
        return false;
    }
    
    /**
     * 处理无效客户端的情况
     * 
     * @param string $serviceUrl
     * @return Response
     */
    protected function handleInvalidClient(string $serviceUrl)
    {
        // 记录无效客户端访问日志
        Log::warning('无效的CAS客户端访问', [
            'service_url' => $serviceUrl,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
        
        // 记录验证失败
        TicketValidationRecord::recordFailure(
            '',
            'SERVICE',
            $serviceUrl,
            TicketValidationRecord::ERROR_INVALID_CLIENT,
            '无效的客户端服务',
            $serviceUrl,
            request()->all(),
            request()->ip(),
            request()->userAgent()
        );
        
        return $this->loginInteraction->showErrorPage(
            new CasException(CasException::UNAUTHORIZED_SERVICE_PROXY, '未授权的服务')
        );
    }
    
    /**
     * 处理已认证用户的单点登录逻辑
     * 
     * @param Request $request
     * @param mixed $user
     * @param Client $client
     * @param string $serviceUrl
     * @return Response
     */
    protected function handleAuthenticatedUser(Request $request, $user, Client $client, string $serviceUrl)
    {
        try {
            // 获取或创建TGT
            $tgt = $this->getOrCreateTgt($user);
            
            if (!$tgt) {
                throw new \Exception('无法创建TGT票据');
            }
            
            // 生成ST
            $st = $this->ticketManager->generateServiceTicket($tgt->tgt, $client->client_name, $serviceUrl);
            
            if (!$st) {
                throw new \Exception('无法生成服务票据');
            }
            
            // 创建或更新登出会话
            $this->createOrUpdateLogoutSession($user, $client, $tgt->tgt, $request);
            
            // 记录成功的认证
            TicketValidationRecord::recordSuccess(
                $st,
                TicketValidationRecord::TICKET_TYPE_ST,
                $client->client_name,
                $serviceUrl,
                $user->id,
                $request->all(),
                ['st' => $st, 'service' => $serviceUrl],
                $request->ip(),
                $request->userAgent()
            );
            
            // 构建重定向URL
            $redirectUrl = $this->buildRedirectUrl($serviceUrl, $st);
            
            // 记录访问日志
            Log::info('CAS单点登录成功', [
                'user_id' => $user->id,
                'client_name' => $client->client_name,
                'service_url' => $serviceUrl,
                'st' => $st,
                'ip' => $request->ip()
            ]);
            
            return redirect($redirectUrl);
            
        } catch (\Exception $e) {
            // 记录错误
            Log::error('处理已认证用户时发生错误', [
                'user_id' => $user->id,
                'client_name' => $client->client_name,
                'service_url' => $serviceUrl,
                'error' => $e->getMessage()
            ]);
            
            // 记录验证失败
            TicketValidationRecord::recordFailure(
                '',
                TicketValidationRecord::TICKET_TYPE_ST,
                $client->client_name,
                TicketValidationRecord::ERROR_INTERNAL_ERROR,
                $e->getMessage(),
                $serviceUrl,
                $request->all(),
                $request->ip(),
                $request->userAgent()
            );
            
            throw $e;
        }
    }
    
    /**
     * 获取或创建TGT
     * 
     * @param mixed $user
     * @return TicketGrantingTicket|null
     */
    protected function getOrCreateTgt($user): ?TicketGrantingTicket
    {
        // 查找用户的有效TGT
        $existingTgt = TicketGrantingTicket::byUser($user->id)
            ->valid()
            ->first();
            
        if ($existingTgt) {
            // 延长TGT的有效期
            $existingTgt->extendExpiration();
            return $existingTgt;
        }
        
        // 创建新的TGT
        return $this->ticketManager->generateTgt($user->id);
    }
    
    /**
     * 创建或更新登出会话
     * 
     * @param mixed $user
     * @param Client $client
     * @param string $tgt
     * @param Request $request
     */
    protected function createOrUpdateLogoutSession($user, Client $client, string $tgt, Request $request): void
    {
        $sessionId = $request->session()->getId();
        
        // 查找现有会话
        $logoutSession = LogoutSession::byUser($user->id)
            ->byClient($client->client_name)
            ->active()
            ->first();
            
        if ($logoutSession) {
            // 更新现有会话
            $logoutSession->update([
                'tgt' => $tgt,
                'session_id' => $sessionId,
                'login_at' => now()
            ]);
            $logoutSession->touch();
        } else {
            // 创建新会话
            LogoutSession::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'client_name' => $client->client_name,
                'tgt' => $tgt,
                'login_at' => now(),
                'is_active' => true
            ]);
        }
    }
    
    /**
     * 构建重定向URL
     * 
     * @param string $serviceUrl
     * @param string $st
     * @return string
     */
    protected function buildRedirectUrl(string $serviceUrl, string $st): string
    {
        $separator = strpos($serviceUrl, '?') !== false ? '&' : '?';
        return $serviceUrl . $separator . 'ticket=' . $st;
    }
}
