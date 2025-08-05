<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Lyn\LaravelCasServer\Contracts\Interactions\UserRegister;
use Lyn\LaravelCasServer\Facades\CasServer;
use Lyn\LaravelCasServer\Models\Token;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\ProxyGrantingTicket;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;
use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Repositories\TokenRepository;
use Lyn\LaravelCasServer\Repositories\ClientRepository;
use Lyn\LaravelCasServer\Services\TicketManger;
use Lyn\LaravelCasServer\Services\AuthService;

/**
 * CAS认证控制器
 * 
 * 处理CAS协议的认证相关接口：
 * 1. 票据验证（/serviceValidate, /proxyValidate）
 * 2. 用户信息获取
 * 3. 代理票据生成（/proxy）
 * 4. 用户注册功能
 * 5. 客户端令牌管理
 */
class AuthController extends Controller
{
    /**
     * 用户注册交互接口
     * 
     * @var UserRegister
     */
    protected $registerInteraction;
    
    /**
     * 令牌仓储
     * 
     * @var TokenRepository
     */
    protected $tokenRepository;
    
    /**
     * 客户端仓储
     * 
     * @var ClientRepository
     */
    protected $clientRepository;
    
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
     * 构造函数
     * 
     * @param UserRegister $registerInteraction 用户注册交互
     * @param TokenRepository $tokenRepository 令牌仓储
     * @param ClientRepository $clientRepository 客户端仓储
     * @param AuthService $authService 认证服务
     * @param TicketManger $ticketManager 票据管理器
     */
    public function __construct(
        UserRegister $registerInteraction,
        TokenRepository $tokenRepository,
        ClientRepository $clientRepository,
        AuthService $authService,
        TicketManger $ticketManager
    ) {
        $this->registerInteraction = $registerInteraction;
        $this->tokenRepository = $tokenRepository;
        $this->clientRepository = $clientRepository;
        $this->authService = $authService;
        $this->ticketManager = $ticketManager;
    }

    /**
     * CAS 1.0协议票据验证接口（已废弃）
     * 
     * 实现CAS协议的/validate端点
     * 仅为兼容性保留，建议使用serviceValidate
     *
     * @param Request $request HTTP请求
     * @return Response 纯文本格式的验证结果
     */
    public function casValidate(Request $request)
    {
        try {
            $ticket = $request->get('ticket', '');
            $service = $request->get('service', '');
            
            // 验证必需参数
            if (empty($ticket) || empty($service)) {
                return response('no\n', 200)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }
            
            // 验证票据
            $validationResult = $this->validateServiceTicket($ticket, $service, $request);
            
            if (!$validationResult['success']) {
                return response('no\n', 200)
                    ->header('Content-Type', 'text/plain; charset=utf-8');
            }
            
            // 返回成功响应（CAS 1.0格式）
            $username = $validationResult['user_data']['id'] ?? $validationResult['user_data']['name'] ?? '';
            return response('yes\n' . $username . '\n', 200)
                ->header('Content-Type', 'text/plain; charset=utf-8');
            
        } catch (\Exception $e) {
            Log::error('CAS 1.0票据验证错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return response('no\n', 200)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }
    }
    
    /**
     * CAS 3.0协议服务票据验证接口
     * 
     * 实现CAS协议的/p3/serviceValidate端点
     * 扩展的票据验证，支持更多属性返回
     *
     * @param Request $request HTTP请求
     * @return Response XML格式的验证结果
     */
    public function p3ServiceValidate(Request $request)
    {
        // CAS 3.0与2.0的serviceValidate基本相同，但支持更多扩展属性
        return $this->serviceValidate($request);
    }
    
    /**
     * CAS 3.0协议代理票据验证接口
     * 
     * 实现CAS协议的/p3/proxyValidate端点
     * 扩展的代理票据验证
     *
     * @param Request $request HTTP请求
     * @return Response XML格式的验证结果
     */
    public function p3ProxyValidate(Request $request)
    {
        // CAS 3.0与2.0的proxyValidate基本相同，但支持更多扩展属性
        return $this->proxyValidate($request);
    }

    /**
     * 显示用户注册页面
     * 
     * @param Request $request HTTP请求
     * @return Response
     */
    public function getRegister(Request $request)
    {
        return $this->registerInteraction->showRegisterPage($request);
    }

    /**
     * 处理用户注册
     * 
     * @param Request $request HTTP请求
     * @return Response
     */
    public function postRegister(Request $request)
    {
        $this->registerInteraction->register($request);
        $from = $request->get('from');
        return redirect(route('cas.login') . '?from=' . $from);
    }

    /**
     * CAS服务票据验证接口
     * 
     * 实现CAS协议的/serviceValidate端点
     * 验证服务票据(ST)的有效性并返回用户信息
     *
     * @param Request $request HTTP请求
     * @return Response XML格式的验证结果
     */
    public function serviceValidate(Request $request)
    {
        try {
            $ticket = $request->get('ticket', '');
            $service = $request->get('service', '');
            $format = $request->get('format', config('casserver.response.default_format', 'JSON')); // 默认使用JSON格式
            
            // 验证必需参数
            if (empty($ticket) || empty($service)) {
                return $this->buildValidationResponse(false, [
                    'code' => 'INVALID_REQUEST',
                    'description' => '缺少必需的参数ticket或service'
                ], null, $format);
            }
            
            // 验证票据
            $validationResult = $this->validateServiceTicket($ticket, $service, $request);
            
            if (!$validationResult['success']) {
                return $this->buildValidationResponse(false, [
                    'code' => $validationResult['error_code'],
                    'description' => $validationResult['error_message']
                ], null, $format);
            }
            
            // 返回成功响应
            return $this->buildValidationResponse(true, null, [
                'user' => $validationResult['user_data'],
                'attributes' => $validationResult['user_attributes'] ?? []
            ], $format);
            
        } catch (\Exception $e) {
            Log::error('服务票据验证错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->buildValidationResponse(false, [
                'code' => 'INTERNAL_ERROR',
                'description' => '票据验证过程中发生内部错误'
            ], null, $request->get('format', config('casserver.response.default_format', 'JSON')));
        }
    }
    
    /**
     * CAS代理票据验证接口
     * 
     * 实现CAS协议的/proxyValidate端点
     * 验证代理票据(PT)的有效性并返回用户信息和代理链
     *
     * @param Request $request HTTP请求
     * @return Response XML格式的验证结果
     */
    public function proxyValidate(Request $request)
    {
        try {
            $ticket = $request->get('ticket', '');
            $service = $request->get('service', '');
            $format = $request->get('format', config('casserver.response.default_format', 'JSON'));
            
            // 验证必需参数
            if (empty($ticket) || empty($service)) {
                return $this->buildValidationResponse(false, [
                    'code' => 'INVALID_REQUEST',
                    'description' => '缺少必需的参数ticket或service'
                ], null, $format);
            }
            
            // 验证票据（支持ST和PT）
            $validationResult = $this->validateTicketForProxy($ticket, $service, $request);
            
            if (!$validationResult['success']) {
                return $this->buildValidationResponse(false, [
                    'code' => $validationResult['error_code'],
                    'description' => $validationResult['error_message']
                ], null, $format);
            }
            
            // 返回成功响应（包含代理链信息）
            return $this->buildValidationResponse(true, null, [
                'user' => $validationResult['user_data'],
                'attributes' => $validationResult['user_attributes'] ?? [],
                'proxies' => $validationResult['proxy_chain'] ?? []
            ], $format);
            
        } catch (\Exception $e) {
            Log::error('代理票据验证错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->buildValidationResponse(false, [
                'code' => 'INTERNAL_ERROR',
                'description' => '代理票据验证过程中发生内部错误'
            ], null, $request->get('format', config('casserver.response.default_format', 'JSON')));
        }
    }
    
    /**
     * CAS代理票据生成接口
     * 
     * 实现CAS协议的/proxy端点
     * 使用PGT生成新的代理票据(PT)
     *
     * @param Request $request HTTP请求
     * @return Response XML格式的代理票据
     */
    public function proxy(Request $request)
    {
        try {
            $pgt = $request->get('pgt', '');
            $targetService = $request->get('targetService', '');
            $format = $request->get('format', config('casserver.response.default_format', 'JSON'));
            
            // 验证必需参数
            if (empty($pgt) || empty($targetService)) {
                return $this->buildProxyResponse(false, [
                    'code' => 'INVALID_REQUEST',
                    'description' => '缺少必需的参数pgt或targetService'
                ], null, $format);
            }
            
            // 查找PGT记录
            $pgtRecord = ProxyGrantingTicket::where('pgt', $pgt)->first();
            
            if (!$pgtRecord) {
                return $this->buildProxyResponse(false, [
                    'code' => 'INVALID_TICKET',
                    'description' => '无效的代理授权票据'
                ], null, $format);
            }
            
            // 检查PGT是否过期
            if ($pgtRecord->isExpired()) {
                return $this->buildProxyResponse(false, [
                    'code' => 'INVALID_TICKET',
                    'description' => '代理授权票据已过期'
                ], null, $format);
            }
            
            // 检查PGT是否已被消费
            if ($pgtRecord->isConsumed()) {
                return $this->buildProxyResponse(false, [
                    'code' => 'INVALID_TICKET',
                    'description' => '代理授权票据已被使用'
                ], null, $format);
            }
            
            // 生成代理票据
            $pt = $pgtRecord->generateProxyTicket($targetService);
            
            if (!$pt) {
                return $this->buildProxyResponse(false, [
                    'code' => 'INTERNAL_ERROR',
                    'description' => '代理票据生成失败'
                ], null, $format);
            }
            
            Log::info('代理票据生成成功', [
                'pgt' => $this->maskTicket($pgt),
                'pt' => $this->maskTicket($pt->pt),
                'target_service' => $targetService,
                'ip' => $request->ip()
            ]);
            
            // 返回成功响应
            return $this->buildProxyResponse(true, null, [
                'proxyTicket' => $pt->pt
            ], $format);
            
        } catch (\Exception $e) {
            Log::error('代理票据生成错误', [
                'error' => $e->getMessage(),
                'pgt' => $this->maskTicket($request->get('pgt', '')),
                'target_service' => $request->get('targetService', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->buildProxyResponse(false, [
                'code' => 'INTERNAL_ERROR',
                'description' => '代理票据生成过程中发生内部错误'
            ], null, $request->get('format', 'XML'));
        }
    }

    /**
     * 获取用户信息接口
     * 
     * 通过有效票据获取用户详细信息
     *
     * @param Request $request HTTP请求
     * @return Response JSON格式的用户信息
     */
    public function getUserInfo(Request $request)
    {
        try {
            $ticket = $request->get('ticket', '');
            
            if (empty($ticket)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => '缺少票据参数'
                    ]
                ], 400);
            }
            
            // 从请求属性中获取验证数据（由中间件设置）
            $validationData = $request->attributes->get('cas_validation_data');
            $userId = $request->attributes->get('cas_user_id');
            
            if (!$validationData || !$userId) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TICKET',
                        'message' => '无效的票据'
                    ]
                ], 401);
            }
            
            // 获取用户信息
            $userModel = config('casserver.user.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => '用户不存在'
                    ]
                ], 404);
            }
            
            // 获取用户数据
            $userData = $this->getUserData($user);
            
            // 添加额外的用户属性
            $userData['attributes'] = $this->getUserAttributes($user);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $userData,
                    'ticket_info' => [
                        'type' => $validationData['ticket_type'],
                        'client' => $validationData['client_name'],
                        'issued_at' => $validationData['ticket_data']['created_at'] ?? null
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取用户信息错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '获取用户信息时发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 记录客户端令牌
     * 
     * 用于客户端应用存储其本地令牌，便于单点登出时通知
     *
     * @param Request $request HTTP请求
     * @return Response JSON响应
     */
    public function recordClientToken(Request $request)
    {
        try {
            $ticket = $request->get('ticket', '');
            $token = $request->get('token', '');
            $clientId = $request->get('client_id', '');
            
            // 验证参数
            $validator = Validator::make($request->all(), [
                'ticket' => 'required|string',
                'token' => 'required|string',
                'client_id' => 'required|string'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => '参数验证失败',
                        'details' => $validator->errors()
                    ]
                ], 400);
            }
            
            // 从请求属性中获取验证数据
            $userId = $request->attributes->get('cas_user_id');
            $validationData = $request->attributes->get('cas_validation_data');
            
            if (!$userId || !$validationData) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TICKET',
                        'message' => '无效的票据'
                    ]
                ], 401);
            }
            
            // 存储客户端令牌
            $this->tokenRepository->tokenStore([
                'user_id' => $userId,
                'session_id' => session()->getId(),
                'client_id' => $clientId,
                'client_token' => $token,
                'ticket' => $ticket,
                'created_at' => now()
            ]);
            
            Log::info('客户端令牌记录成功', [
                'user_id' => $userId,
                'client_id' => $clientId,
                'ticket' => $this->maskTicket($ticket),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '客户端令牌记录成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('记录客户端令牌错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'client_id' => $request->get('client_id', ''),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => '记录客户端令牌时发生内部错误'
                ]
            ], 500);
        }
    }
    
    /**
     * 验证服务票据
     * 
     * @param string $ticket 票据
     * @param string $service 服务URL
     * @param Request $request 请求
     * @return array 验证结果
     */
    protected function validateServiceTicket(string $ticket, string $service, Request $request): array
    {
        // 查找ST记录
        $st = ServiceTicket::where('st', $ticket)->first();
        
        if (!$st) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '票据不存在'
            ];
        }
        
        // 检查票据是否过期
        if ($st->isExpired()) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '票据已过期'
            ];
        }
        
        // 检查票据是否已被消费
        if ($st->isConsumed()) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '票据已被使用'
            ];
        }
        
        // 验证服务URL匹配
        if ($st->service_url !== $service) {
            return [
                'success' => false,
                'error_code' => 'INVALID_SERVICE',
                'error_message' => '服务URL不匹配'
            ];
        }
        
        // 消费票据
        $st->consume();
        
        // 获取用户信息
        $user = $st->getUser();
        
        if (!$user) {
            return [
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'error_message' => '用户不存在'
            ];
        }
        
        // 记录验证成功
        TicketValidationRecord::recordSuccess(
            $ticket,
            TicketValidationRecord::TICKET_TYPE_ST,
            $st->client_name,
            $service,
            $user->id,
            $request->all(),
            ['validation_type' => 'serviceValidate'],
            $request->ip(),
            $request->userAgent()
        );
        
        return [
            'success' => true,
            'user_data' => $this->getUserData($user),
            'user_attributes' => $this->getUserAttributes($user)
        ];
    }
    
    /**
     * 验证票据（支持ST和PT）
     * 
     * @param string $ticket 票据
     * @param string $service 服务URL
     * @param Request $request 请求
     * @return array 验证结果
     */
    protected function validateTicketForProxy(string $ticket, string $service, Request $request): array
    {
        // 确定票据类型
        if (strpos($ticket, 'ST-') === 0) {
            return $this->validateServiceTicket($ticket, $service, $request);
        } elseif (strpos($ticket, 'PT-') === 0) {
            return $this->validateProxyTicket($ticket, $service, $request);
        }
        
        return [
            'success' => false,
            'error_code' => 'INVALID_TICKET',
            'error_message' => '无效的票据格式'
        ];
    }
    
    /**
     * 验证代理票据
     * 
     * @param string $ticket 票据
     * @param string $service 服务URL
     * @param Request $request 请求
     * @return array 验证结果
     */
    protected function validateProxyTicket(string $ticket, string $service, Request $request): array
    {
        // 查找PT记录
        $pt = ProxyTicket::where('pt', $ticket)->first();
        
        if (!$pt) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '代理票据不存在'
            ];
        }
        
        // 检查票据是否过期
        if ($pt->isExpired()) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '代理票据已过期'
            ];
        }
        
        // 检查票据是否已被消费
        if ($pt->isConsumed()) {
            return [
                'success' => false,
                'error_code' => 'INVALID_TICKET',
                'error_message' => '代理票据已被使用'
            ];
        }
        
        // 验证目标服务URL匹配
        if ($pt->target_service !== $service) {
            return [
                'success' => false,
                'error_code' => 'INVALID_SERVICE',
                'error_message' => '目标服务URL不匹配'
            ];
        }
        
        // 消费票据
        $pt->consume();
        
        // 获取用户信息
        $user = $pt->getUser();
        
        if (!$user) {
            return [
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'error_message' => '用户不存在'
            ];
        }
        
        // 记录验证成功
        TicketValidationRecord::recordSuccess(
            $ticket,
            TicketValidationRecord::TICKET_TYPE_PT,
            $pt->target_service,
            $service,
            $user->id,
            $request->all(),
            ['validation_type' => 'proxyValidate', 'proxy_chain' => $pt->getProxyChainArray()],
            $request->ip(),
            $request->userAgent()
        );
        
        return [
            'success' => true,
            'user_data' => $this->getUserData($user),
            'user_attributes' => $this->getUserAttributes($user),
            'proxy_chain' => $pt->getProxyChainArray()
        ];
    }
    
    /**
     * 构建验证响应
     * 
     * @param bool $success 是否成功
     * @param array|null $error 错误信息
     * @param array|null $data 成功数据
     * @param string $format 响应格式
     * @return Response
     */
    protected function buildValidationResponse(bool $success, ?array $error, ?array $data, string $format = 'XML')
    {
        if (strtoupper($format) === 'JSON') {
            return response()->json([
                'serviceResponse' => [
                    'success' => $success,
                    'error' => $error,
                    'data' => $data
                ]
            ]);
        }
        
        // 默认XML格式
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">' . "\n";
        
        if ($success && $data) {
            $xml .= '  <cas:authenticationSuccess>' . "\n";
            $xml .= '    <cas:user>' . htmlspecialchars($data['user']['id'] ?? $data['user']['name'] ?? '') . '</cas:user>' . "\n";
            
            if (!empty($data['attributes'])) {
                $xml .= '    <cas:attributes>' . "\n";
                foreach ($data['attributes'] as $key => $value) {
                    $xml .= '      <cas:' . htmlspecialchars($key) . '>' . htmlspecialchars($value) . '</cas:' . htmlspecialchars($key) . '>' . "\n";
                }
                $xml .= '    </cas:attributes>' . "\n";
            }
            
            if (!empty($data['proxies'])) {
                $xml .= '    <cas:proxies>' . "\n";
                foreach ($data['proxies'] as $proxy) {
                    $xml .= '      <cas:proxy>' . htmlspecialchars($proxy) . '</cas:proxy>' . "\n";
                }
                $xml .= '    </cas:proxies>' . "\n";
            }
            
            $xml .= '  </cas:authenticationSuccess>' . "\n";
        } else {
            $xml .= '  <cas:authenticationFailure code="' . htmlspecialchars($error['code']) . '">' . "\n";
            $xml .= '    ' . htmlspecialchars($error['description']) . "\n";
            $xml .= '  </cas:authenticationFailure>' . "\n";
        }
        
        $xml .= '</cas:serviceResponse>';
        
        return response($xml, $success ? 200 : 401)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
    
    /**
     * 构建代理响应
     * 
     * @param bool $success 是否成功
     * @param array|null $error 错误信息
     * @param array|null $data 成功数据
     * @param string $format 响应格式
     * @return Response
     */
    protected function buildProxyResponse(bool $success, ?array $error, ?array $data, string $format = 'XML')
    {
        if (strtoupper($format) === 'JSON') {
            return response()->json([
                'proxyResponse' => [
                    'success' => $success,
                    'error' => $error,
                    'data' => $data
                ]
            ]);
        }
        
        // 默认XML格式
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">' . "\n";
        
        if ($success && $data) {
            $xml .= '  <cas:proxySuccess>' . "\n";
            $xml .= '    <cas:proxyTicket>' . htmlspecialchars($data['proxyTicket']) . '</cas:proxyTicket>' . "\n";
            $xml .= '  </cas:proxySuccess>' . "\n";
        } else {
            $xml .= '  <cas:proxyFailure code="' . htmlspecialchars($error['code']) . '">' . "\n";
            $xml .= '    ' . htmlspecialchars($error['description']) . "\n";
            $xml .= '  </cas:proxyFailure>' . "\n";
        }
        
        $xml .= '</cas:serviceResponse>';
        
        return response($xml, $success ? 200 : 401)
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }
    
    /**
     * 获取用户数据
     * 
     * @param mixed $user 用户模型实例
     * @return array 用户数据
     */
    protected function getUserData($user): array
    {
        // 获取配置的用户信息字段
        $userInfoFields = config('casserver.user.user_info', ['id', 'name', 'email']);
        
        // 检查是否有自定义用户数据处理器
        $customHandler = config('casserver.user.custom_user_data_handler');
        if ($customHandler && is_callable($customHandler)) {
            return call_user_func($customHandler, $user, $userInfoFields);
        }
        
        return $user->only($userInfoFields);
    }
    
    /**
     * 获取用户属性
     * 
     * @param mixed $user 用户模型实例
     * @return array 用户属性
     */
    protected function getUserAttributes($user): array
    {
        // 检查是否有自定义用户信息处理器
        $customHandler = config('casserver.user.custom_user_info_handler');
        if ($customHandler && is_callable($customHandler)) {
            return call_user_func($customHandler, $user);
        }
        
        $attributes = [];
        
        // 基本属性
        if ($user->email) {
            $attributes['email'] = $user->email;
        }
        
        if (isset($user->name)) {
            $attributes['displayName'] = $user->name;
        }
        
        // 从配置中获取额外属性
        $extraAttributes = config('casserver.user.extra_attributes', []);
        foreach ($extraAttributes as $attribute) {
            if (isset($user->$attribute)) {
                $attributes[$attribute] = $user->$attribute;
            }
        }
        
        return $attributes;
    }
    
    /**
     * 掩码票据字符串（用于日志记录）
     * 
     * @param string $ticket
     * @return string
     */
    protected function maskTicket(string $ticket): string
    {
        if (strlen($ticket) <= 8) {
            return str_repeat('*', strlen($ticket));
        }
        
        return substr($ticket, 0, 4) . str_repeat('*', strlen($ticket) - 8) . substr($ticket, -4);
    }
}
