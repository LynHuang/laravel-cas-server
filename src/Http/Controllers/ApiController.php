<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\TicketGrantingTicket;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;
use Lyn\LaravelCasServer\Repositories\TicketRepository;
use Lyn\LaravelCasServer\Services\AuthService;

/**
 * CAS API控制器
 * 
 * 提供CAS服务器的API接口，供第三方应用集成：
 * 1. 令牌验证API
 * 2. 用户信息获取API
 * 3. 票据状态查询API
 * 4. 批量验证API
 */
class ApiController extends Controller
{
    /**
     * 票据仓储
     * 
     * @var TicketRepository
     */
    protected $ticketRepository;
    
    /**
     * 认证服务
     * 
     * @var AuthService
     */
    protected $authService;

    /**
     * 构造函数
     * 
     * @param TicketRepository $ticketRepository 票据仓储
     * @param AuthService $authService 认证服务
     */
    public function __construct(
        TicketRepository $ticketRepository,
        AuthService $authService
    ) {
        $this->ticketRepository = $ticketRepository;
        $this->authService = $authService;
    }

    /**
     * 验证令牌API
     * 
     * 供第三方应用验证CAS令牌的有效性
     *
     * @param Request $request HTTP请求
     * @return Response JSON格式的验证结果
     */
    public function validateToken(Request $request)
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'service' => 'required|url',
                'type' => 'sometimes|in:ST,PT,TGT' // 可选的票据类型
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => '请求参数验证失败',
                        'details' => $validator->errors()
                    ]
                ], 400);
            }
            
            $token = $request->get('token');
            $service = $request->get('service');
            $expectedType = $request->get('type');
            
            // 确定票据类型
            $ticketType = $this->determineTicketType($token);
            
            if ($expectedType && $ticketType !== $expectedType) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_TICKET_TYPE',
                        'message' => '票据类型不匹配',
                        'expected' => $expectedType,
                        'actual' => $ticketType
                    ]
                ], 400);
            }
            
            // 验证票据
            $validationResult = $this->validateTicketByType($token, $service, $ticketType, $request);
            
            if (!$validationResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $validationResult['error_code'],
                        'message' => $validationResult['error_message']
                    ]
                ], 401);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'valid' => true,
                    'ticket_type' => $ticketType,
                    'user' => $validationResult['user_data'],
                    'attributes' => $validationResult['user_attributes'] ?? [],
                    'validation_time' => now()->toISOString(),
                    'expires_at' => $validationResult['expires_at'] ?? null
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('API令牌验证错误', [
                'error' => $e->getMessage(),
                'token' => $this->maskToken($request->get('token', '')),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'API令牌验证过程中发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 根据票据获取用户信息API
     * 
     * 供第三方应用获取用户详细信息
     *
     * @param Request $request HTTP请求
     * @param string $ticket 票据
     * @return Response JSON格式的用户信息
     */
    public function getUserByTicket(Request $request, string $ticket)
    {
        try {
            if (empty($ticket)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => '票据参数不能为空'
                    ]
                ], 400);
            }
            
            // 查找票据记录
            $ticketRecord = $this->findTicketRecord($ticket);
            
            if (!$ticketRecord) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TICKET_NOT_FOUND',
                        'message' => '票据不存在'
                    ]
                ], 404);
            }
            
            // 检查票据状态
            if ($ticketRecord->isExpired()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'TICKET_EXPIRED',
                        'message' => '票据已过期'
                    ]
                ], 401);
            }
            
            // 获取用户信息
            $user = $ticketRecord->getUser();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => '用户不存在'
                    ]
                ], 404);
            }
            
            // 构建响应数据
            $userData = $user->only(config('casserver.user.user_info', ['id', 'name', 'email']));
            $userData['attributes'] = $this->getUserAttributes($user);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $userData,
                    'ticket_info' => [
                        'type' => $this->determineTicketType($ticket),
                        'issued_at' => $ticketRecord->created_at->toISOString(),
                        'expires_at' => $ticketRecord->getExpirationTime(),
                        'is_consumed' => $ticketRecord->isConsumed(),
                        'client_name' => $ticketRecord->client_name ?? null
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('API获取用户信息错误', [
                'error' => $e->getMessage(),
                'ticket' => $this->maskToken($ticket),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'API获取用户信息过程中发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 批量验证票据API
     * 
     * 供第三方应用批量验证多个票据
     *
     * @param Request $request HTTP请求
     * @return Response JSON格式的批量验证结果
     */
    public function batchValidateTokens(Request $request)
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'tokens' => 'required|array|max:50', // 限制最多50个票据
                'tokens.*' => 'required|string',
                'service' => 'required|url'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => '请求参数验证失败',
                        'details' => $validator->errors()
                    ]
                ], 400);
            }
            
            $tokens = $request->get('tokens');
            $service = $request->get('service');
            $results = [];
            
            foreach ($tokens as $token) {
                try {
                    $ticketType = $this->determineTicketType($token);
                    $validationResult = $this->validateTicketByType($token, $service, $ticketType, $request);
                    
                    $results[] = [
                        'token' => $this->maskToken($token),
                        'valid' => $validationResult['success'],
                        'ticket_type' => $ticketType,
                        'user' => $validationResult['success'] ? $validationResult['user_data'] : null,
                        'error' => $validationResult['success'] ? null : [
                            'code' => $validationResult['error_code'],
                            'message' => $validationResult['error_message']
                        ]
                    ];
                } catch (\Exception $e) {
                    $results[] = [
                        'token' => $this->maskToken($token),
                        'valid' => false,
                        'error' => [
                            'code' => 'VALIDATION_ERROR',
                            'message' => '票据验证过程中发生错误'
                        ]
                    ];
                }
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total' => count($results),
                        'valid' => count(array_filter($results, fn($r) => $r['valid'])),
                        'invalid' => count(array_filter($results, fn($r) => !$r['valid']))
                    ]
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('API批量验证错误', [
                'error' => $e->getMessage(),
                'token_count' => count($request->get('tokens', [])),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'API批量验证过程中发生内部错误'
                ]
            ], 500);
        }
    }

    /**
     * 确定票据类型
     * 
     * @param string $ticket 票据
     * @return string 票据类型
     */
    protected function determineTicketType(string $ticket): string
    {
        if (strpos($ticket, 'ST-') === 0) {
            return 'ST';
        } elseif (strpos($ticket, 'PT-') === 0) {
            return 'PT';
        } elseif (strpos($ticket, 'TGT-') === 0) {
            return 'TGT';
        } elseif (strpos($ticket, 'PGT-') === 0) {
            return 'PGT';
        }
        
        return 'UNKNOWN';
    }

    /**
     * 根据类型验证票据
     * 
     * @param string $ticket 票据
     * @param string $service 服务URL
     * @param string $type 票据类型
     * @param Request $request 请求
     * @return array 验证结果
     */
    protected function validateTicketByType(string $ticket, string $service, string $type, Request $request): array
    {
        switch ($type) {
            case 'ST':
                return $this->validateServiceTicket($ticket, $service, $request);
            case 'PT':
                return $this->validateProxyTicket($ticket, $service, $request);
            case 'TGT':
                return $this->validateTicketGrantingTicket($ticket, $request);
            default:
                return [
                    'success' => false,
                    'error_code' => 'UNSUPPORTED_TICKET_TYPE',
                    'error_message' => '不支持的票据类型'
                ];
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
        $st = ServiceTicket::where('st', $ticket)->first();
        
        if (!$st) {
            return [
                'success' => false,
                'error_code' => 'TICKET_NOT_FOUND',
                'error_message' => '服务票据不存在'
            ];
        }
        
        if ($st->isExpired()) {
            return [
                'success' => false,
                'error_code' => 'TICKET_EXPIRED',
                'error_message' => '服务票据已过期'
            ];
        }
        
        if ($st->service_url !== $service) {
            return [
                'success' => false,
                'error_code' => 'SERVICE_MISMATCH',
                'error_message' => '服务URL不匹配'
            ];
        }
        
        $user = $st->getUser();
        if (!$user) {
            return [
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'error_message' => '用户不存在'
            ];
        }
        
        return [
            'success' => true,
            'user_data' => $user->only(config('casserver.user.user_info', ['id', 'name', 'email'])),
            'user_attributes' => $this->getUserAttributes($user),
            'expires_at' => $st->getExpirationTime()
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
        $pt = ProxyTicket::where('pt', $ticket)->first();
        
        if (!$pt) {
            return [
                'success' => false,
                'error_code' => 'TICKET_NOT_FOUND',
                'error_message' => '代理票据不存在'
            ];
        }
        
        if ($pt->isExpired()) {
            return [
                'success' => false,
                'error_code' => 'TICKET_EXPIRED',
                'error_message' => '代理票据已过期'
            ];
        }
        
        if ($pt->target_service !== $service) {
            return [
                'success' => false,
                'error_code' => 'SERVICE_MISMATCH',
                'error_message' => '目标服务URL不匹配'
            ];
        }
        
        $user = $pt->getUser();
        if (!$user) {
            return [
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'error_message' => '用户不存在'
            ];
        }
        
        return [
            'success' => true,
            'user_data' => $user->only(config('casserver.user.user_info', ['id', 'name', 'email'])),
            'user_attributes' => $this->getUserAttributes($user),
            'proxy_chain' => $pt->getProxyChainArray(),
            'expires_at' => $pt->getExpirationTime()
        ];
    }

    /**
     * 验证票据授权票据
     * 
     * @param string $ticket 票据
     * @param Request $request 请求
     * @return array 验证结果
     */
    protected function validateTicketGrantingTicket(string $ticket, Request $request): array
    {
        $tgt = TicketGrantingTicket::where('tgt', $ticket)->first();
        
        if (!$tgt) {
            return [
                'success' => false,
                'error_code' => 'TICKET_NOT_FOUND',
                'error_message' => '票据授权票据不存在'
            ];
        }
        
        if ($tgt->isExpired()) {
            return [
                'success' => false,
                'error_code' => 'TICKET_EXPIRED',
                'error_message' => '票据授权票据已过期'
            ];
        }
        
        $user = $tgt->getUser();
        if (!$user) {
            return [
                'success' => false,
                'error_code' => 'USER_NOT_FOUND',
                'error_message' => '用户不存在'
            ];
        }
        
        return [
            'success' => true,
            'user_data' => $user->only(config('casserver.user.user_info', ['id', 'name', 'email'])),
            'user_attributes' => $this->getUserAttributes($user),
            'expires_at' => $tgt->getExpirationTime()
        ];
    }

    /**
     * 查找票据记录
     * 
     * @param string $ticket 票据
     * @return mixed 票据记录
     */
    protected function findTicketRecord(string $ticket)
    {
        $type = $this->determineTicketType($ticket);
        
        switch ($type) {
            case 'ST':
                return ServiceTicket::where('st', $ticket)->first();
            case 'PT':
                return ProxyTicket::where('pt', $ticket)->first();
            case 'TGT':
                return TicketGrantingTicket::where('tgt', $ticket)->first();
            default:
                return null;
        }
    }

    /**
     * 获取用户属性
     * 
     * @param User $user 用户
     * @return array 用户属性
     */
    protected function getUserAttributes(User $user): array
    {
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
     * 掩码令牌字符串（用于日志记录）
     * 
     * @param string $token
     * @return string
     */
    protected function maskToken(string $token): string
    {
        if (strlen($token) <= 8) {
            return str_repeat('*', strlen($token));
        }
        
        return substr($token, 0, 4) . str_repeat('*', strlen($token) - 8) . substr($token, -4);
    }
}