<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Redirect;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Events\CasLogoutEvent;
use Lyn\LaravelCasServer\Events\CasUserLoggedOutEvent;
use Lyn\LaravelCasServer\Facades\CasServer;
use Lyn\LaravelCasServer\Services\AuthService;
use Lyn\LaravelCasServer\Services\TicketManger;
use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Lyn\LaravelCasServer\Models\LogoutSession;
use Lyn\LaravelCasServer\Repositories\ClientRepository;

/**
 * CAS登录控制器
 * 
 * 处理CAS协议的登录流程：
 * 1. 显示登录页面
 * 2. 处理用户登录认证
 * 3. 生成TGT和ST票据
 * 4. 处理单点登出
 * 5. 管理登录会话
 */
class LoginController extends Controller
{
    /**
     * 用户登录交互接口
     * 
     * @var UserLogin
     */
    protected $loginInteraction;
    
    /**
     * 认证服务
     * 
     * @var AuthService
     */
    protected $authService;
    
    /**
     * 票据管理器
     * 
     * @var TicketManger
     */
    protected $ticketManager;
    
    /**
     * 客户端仓储
     * 
     * @var ClientRepository
     */
    protected $clientRepository;

    /**
     * 构造函数
     * 
     * @param UserLogin $loginInteraction 用户登录交互
     * @param AuthService $authService 认证服务
     * @param TicketManger $ticketManager 票据管理器
     * @param ClientRepository $clientRepository 客户端仓储
     */
    public function __construct(
        UserLogin $loginInteraction,
        AuthService $authService,
        TicketManger $ticketManager,
        ClientRepository $clientRepository
    ) {
        $this->loginInteraction = $loginInteraction;
        $this->authService = $authService;
        $this->ticketManager = $ticketManager;
        $this->clientRepository = $clientRepository;
    }

    /**
     * 显示登录页面
     * 
     * CAS协议登录流程：
     * 1. 检查用户是否已登录
     * 2. 验证service参数
     * 3. 如果已登录且有有效TGT，直接生成ST并重定向
     * 4. 否则显示登录页面
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function getLogin(Request $request)
    {
        try {
            $service = $request->get('service', '');
            $renew = $request->get('renew', false);
            $gateway = $request->get('gateway', false);
            
            // 记录登录请求
            Log::info('CAS登录请求', [
                'service' => $service,
                'renew' => $renew,
                'gateway' => $gateway,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            // 验证service参数（如果提供）
            if (!empty($service)) {
                $client = $this->clientRepository->findByServiceUrl($service);
                if (!$client || !$client->isEnabled()) {
                    Log::warning('无效的服务URL', [
                        'service' => $service,
                        'ip' => $request->ip()
                    ]);
                    
                    return view('casserver::error', [
                        'title' => '服务未授权',
                        'message' => '请求的服务未在CAS服务器中注册或已被禁用。',
                        'error_code' => 'UNAUTHORIZED_SERVICE'
                    ]);
                }
            }
            
            // 检查用户是否已登录且有有效的TGT
            if (Auth::check() && !$renew) {
                $user = Auth::user();
                $tgt = $this->authService->getValidTGT($user->id);
                
                if ($tgt && !empty($service)) {
                    // 获取客户端信息
                    $client = $this->clientRepository->findByServiceUrl($service);
                    if (!$client || !$client->isEnabled()) {
                        Log::warning('无效的服务URL', [
                            'service' => $service,
                            'ip' => $request->ip()
                        ]);
                        
                        return view('casserver::error', [
                            'title' => '服务未授权',
                            'message' => '请求的服务未在CAS服务器中注册或已被禁用。',
                            'error_code' => 'UNAUTHORIZED_SERVICE'
                        ]);
                    }
                    
                    // 用户已登录且有有效TGT，直接生成ST并重定向
                    $st = $this->authService->generateServiceTicket(
                        $tgt->tgt,
                        $service,
                        $client->client_name
                    );
                    
                    if ($st) {
                        Log::info('自动生成ST票据', [
                            'user_id' => $user->id,
                            'service' => $service,
                            'st' => substr($st, 0, 10) . '...',
                            'ip' => $request->ip()
                        ]);
                        
                        return $this->redirectToService($service, $st);
                    }
                } elseif ($tgt && empty($service)) {
                    // 用户已登录但没有service参数，显示已登录状态
                    return view('casserver::logged-in', [
                        'user' => $user,
                        'tgt' => $tgt
                    ]);
                }
            }
            
            // Gateway模式：如果用户未登录且启用了gateway，直接重定向回service
            if ($gateway && !Auth::check() && !empty($service)) {
                Log::info('Gateway模式重定向', [
                    'service' => $service,
                    'ip' => $request->ip()
                ]);
                
                return redirect($service);
            }
            
            // 显示登录页面
            return $this->loginInteraction->showLoginPage($request);
            
        } catch (\Exception $e) {
            Log::error('登录页面显示错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return view('casserver::error', [
                'title' => '系统错误',
                'message' => '登录页面加载失败，请稍后重试。',
                'error_code' => 'SYSTEM_ERROR'
            ]);
        }
    }

    /**
     * 处理用户登录
     * 
     * CAS协议登录处理流程：
     * 1. 验证登录凭据
     * 2. 生成TGT票据
     * 3. 如果有service参数，生成ST票据
     * 4. 创建登录会话
     * 5. 重定向到目标服务或显示成功页面
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function postLogin(Request $request)
    {
        try {
            $service = $request->get('service', '');
            $username = $request->get('username', '');
            $password = $request->get('password', '');
            
            // 验证输入参数
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|max:255',
                'password' => 'required|string|min:1'
            ], [
                'username.required' => '请输入用户名',
                'password.required' => '请输入密码'
            ]);
            
            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }
            
            // 验证service参数（如果提供）
            $client = null;
            if (!empty($service)) {
                $client = $this->clientRepository->findByServiceUrl($service);
                if (!$client || !$client->isEnabled()) {
                    return back()->withErrors([
                        'service' => '请求的服务未授权或已被禁用'
                    ])->withInput();
                }
            }
            
            // 尝试登录
            $loginResult = $this->authService->authenticate($username, $password);
            
            if (!$loginResult['success']) {
                Log::warning('用户登录失败', [
                    'username' => $username,
                    'reason' => $loginResult['message'],
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return back()->withErrors([
                    'login' => $loginResult['message']
                ])->withInput(['username' => $username]);
            }
            
            $user = $loginResult['user'];
            $tgt = $loginResult['tgt'];
            
            // 记录成功登录
            Log::info('用户登录成功', [
                'user_id' => $user->id,
                'username' => $user->username ?? $user->email,
                'tgt' => substr($tgt, 0, 10) . '...',
                'service' => $service,
                'ip' => $request->ip()
            ]);
            
            // 如果有service参数，生成ST票据并重定向
            if (!empty($service) && $client) {
                $st = $this->authService->generateServiceTicket(
                    $tgt,
                    $service,
                    $client->client_name
                );
                
                if ($st) {
                    // 创建登录会话记录
                    $this->createLoginSession($user, $client, $tgt, $request);
                    
                    return $this->redirectToService($service, $st);
                } else {
                    Log::error('ST票据生成失败', [
                        'user_id' => $user->id,
                        'tgt' => substr($tgt, 0, 10) . '...',
                        'service' => $service
                    ]);
                    
                    return back()->withErrors([
                        'login' => '票据生成失败，请重试'
                    ]);
                }
            }
            
            // 没有service参数，显示登录成功页面
            return view('casserver::logged-in', [
                'user' => $user,
                'tgt' => $tgt
            ]);
            
        } catch (\Exception $e) {
            Log::error('登录处理错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'username' => $request->get('username', ''),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return back()->withErrors([
                'login' => '登录过程中发生错误，请稍后重试'
            ])->withInput(['username' => $request->get('username', '')]);
        }
    }

    /**
     * 处理用户登出
     * 
     * CAS协议登出流程：
     * 1. 触发单点登出事件
     * 2. 清理TGT和相关票据
     * 3. 注销用户会话
     * 4. 重定向到登录页面或指定URL
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function logout(Request $request)
    {
        try {
            $service = $request->get('service', '');
            $url = $request->get('url', '');
            $user = Auth::user();
            
            Log::info('用户登出请求', [
                'user_id' => $user ? $user->id : null,
                'service' => $service,
                'url' => $url,
                'ip' => $request->ip()
            ]);
            
            // 如果用户已登录，执行登出流程
            if ($user) {
                // 获取当前用户的TGT
                $tgt = $this->authService->getValidTGT($user->id);
                
                // 触发用户登出事件
                event(new CasUserLoggedOutEvent(
                    $user,
                    $tgt,
                    'user_logout',
                    $request->ip(),
                    $request->userAgent(),
                    Session::getId()
                ));
                
                if ($tgt) {
                    // 触发CAS单点登出事件
                    if (config('casserver.user.cas_slo', true)) {
                        event(new CasLogoutEvent(
                            Session::getId(),
                            $user,
                            $tgt,
                            'user_logout',
                            $request->ip(),
                            $request->userAgent()
                        ));
                    }
                    
                    // 执行单点登出
                    $this->performSingleLogout($tgt, $request);
                }
                
                // 注销用户会话
                $this->authService->logout($user->id);
            }
            
            // 使用登录交互接口执行登出
            $this->loginInteraction->logout($request);
            
            // 确定重定向URL
            $redirectUrl = $this->determineLogoutRedirectUrl($service, $url, $request);
            
            Log::info('用户登出完成', [
                'user_id' => $user ? $user->id : null,
                'redirect_url' => $redirectUrl,
                'ip' => $request->ip()
            ]);
            
            return redirect($redirectUrl);
            
        } catch (\Exception $e) {
            Log::error('登出处理错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::check() ? Auth::id() : null,
                'ip' => $request->ip()
            ]);
            
            // 即使出错也要尝试登出
            Auth::logout();
            Session::flush();
            
            return redirect(route('cas.login'));
        }
    }
    
    /**
     * 重定向到目标服务
     * 
     * @param string $service 服务URL
     * @param string $ticket 票据
     * @return Response
     */
    protected function redirectToService(string $service, string $ticket)
    {
        $separator = strpos($service, '?') !== false ? '&' : '?';
        $redirectUrl = $service . $separator . 'ticket=' . urlencode($ticket);
        
        return redirect($redirectUrl);
    }
    
    /**
     * 创建登录会话记录
     * 
     * @param User $user 用户
     * @param Client $client 客户端
     * @param string $tgt TGT票据
     * @param Request $request 请求
     */
    protected function createLoginSession(User $user, Client $client, string $tgt, Request $request)
    {
        try {
            LogoutSession::create([
                'user_id' => $user->id,
                'client_name' => $client->client_name,
                'tgt' => $tgt,
                'session_id' => session()->getId(),
                'login_at' => now(),
                'is_active' => true,
                'session_data' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'login_time' => now()->toISOString()
                ]
            ]);
        } catch (\Exception $e) {
            Log::warning('创建登录会话记录失败', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'client_name' => $client->client_name
            ]);
        }
    }
    
    /**
     * 执行单点登出
     * 
     * @param TicketGrantingTicket $tgt TGT票据
     * @param Request $request 请求
     */
    protected function performSingleLogout(TicketGrantingTicket $tgt, Request $request)
    {
        try {
            // 获取所有活跃的登录会话
            $sessions = LogoutSession::where('tgt', $tgt->tgt)
                ->where('is_active', true)
                ->get();
            
            foreach ($sessions as $session) {
                // 执行单点登出
                $session->performSingleLogout();
            }
            
            // 清理TGT和相关票据
            $this->ticketManager->removeTGT($tgt->tgt);
            
        } catch (\Exception $e) {
            Log::error('单点登出执行失败', [
                'error' => $e->getMessage(),
                'tgt' => substr($tgt->tgt, 0, 10) . '...'
            ]);
        }
    }
    
    /**
     * 确定登出重定向URL
     * 
     * @param string $service 服务URL
     * @param string $url 指定URL
     * @param Request $request 请求
     * @return string
     */
    protected function determineLogoutRedirectUrl(string $service, string $url, Request $request): string
    {
        // 优先使用url参数
        if (!empty($url)) {
            // 验证URL是否在允许的域名列表中
            if ($this->isAllowedLogoutUrl($url)) {
                return $url;
            }
        }
        
        // 其次使用service参数
        if (!empty($service)) {
            $client = $this->clientRepository->findByServiceUrl($service);
            if ($client && $client->isEnabled()) {
                return $service;
            }
        }
        
        // 最后使用from参数或默认登录页面
        $from = $request->get('from', '');
        if (!empty($from) && $this->isAllowedLogoutUrl($from)) {
            return $from;
        }
        
        return route('cas.login');
    }
    
    /**
     * 检查URL是否在允许的登出重定向列表中
     * 
     * @param string $url
     * @return bool
     */
    protected function isAllowedLogoutUrl(string $url): bool
    {
        $allowedDomains = config('casserver.logout.allowed_domains', []);
        
        if (empty($allowedDomains)) {
            return true; // 如果没有配置限制，允许所有URL
        }
        
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        $host = $parsedUrl['host'];
        
        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }
        
        return false;
    }
}
