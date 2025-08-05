<?php

namespace Lyn\LaravelCasServer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Lyn\LaravelCasServer\Models\ServiceTicket;
use Lyn\LaravelCasServer\Models\ProxyTicket;
use Lyn\LaravelCasServer\Models\TicketValidationRecord;
use Lyn\LaravelCasServer\Services\TicketManger;
use Lyn\LaravelCasServer\Repositories\ClientRepository;

/**
 * CAS票据检查中间件
 * 
 * 验证传入的CAS票据的有效性：
 * 1. 验证票据格式和存在性
 * 2. 检查票据是否过期
 * 3. 验证票据是否已被消费
 * 4. 检查IP地址一致性（可选）
 * 5. 验证票据与服务的匹配性
 * 6. 记录验证结果和审计日志
 */
class CasTicketCheck
{
    /**
     * 票据管理服务
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
     * @param TicketManger $ticketManager 票据管理器
     * @param ClientRepository $clientRepository 客户端仓储
     */
    public function __construct(
        TicketManger $ticketManager,
        ClientRepository $clientRepository
    ) {
        $this->ticketManager = $ticketManager;
        $this->clientRepository = $clientRepository;
    }

    /**
     * 处理传入的请求
     * 
     * 验证CAS票据的完整流程：
     * 1. 提取票据参数
     * 2. 验证票据格式
     * 3. 检查票据有效性
     * 4. 验证IP地址（如果启用）
     * 5. 验证服务匹配性
     * 6. 消费票据（ST是一次性的）
     * 7. 记录验证结果
     *
     * @param Request $request HTTP请求
     * @param Closure $next 下一个中间件
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // 提取票据参数
            $ticket = $request->get('ticket', '');
            $service = $request->get('service', '');
            
            // 基本验证
            if (empty($ticket)) {
                return $this->handleValidationFailure(
                    $ticket,
                    $service,
                    TicketValidationRecord::ERROR_INVALID_TICKET,
                    '缺少票据参数',
                    $request
                );
            }
            
            // 验证票据
            $validationResult = $this->validateTicket($ticket, $service, $request);
            
            if (!$validationResult['valid']) {
                return $this->handleValidationFailure(
                    $ticket,
                    $service,
                    $validationResult['error_code'],
                    $validationResult['error_message'],
                    $request
                );
            }
            
            // 将验证结果添加到请求中，供后续中间件使用
            $request->attributes->add([
                'cas_ticket' => $ticket,
                'cas_ticket_type' => $validationResult['ticket_type'],
                'cas_user_id' => $validationResult['user_id'],
                'cas_client_name' => $validationResult['client_name'],
                'cas_validation_data' => $validationResult
            ]);
            
            // 记录成功验证
            TicketValidationRecord::recordSuccess(
                $ticket,
                $validationResult['ticket_type'],
                $validationResult['client_name'],
                $service,
                $validationResult['user_id'],
                $request->all(),
                $validationResult,
                $request->ip(),
                $request->userAgent()
            );
            
            // 记录访问日志
            Log::info('CAS票据验证成功', [
                'ticket' => $this->maskTicket($ticket),
                'ticket_type' => $validationResult['ticket_type'],
                'user_id' => $validationResult['user_id'],
                'client_name' => $validationResult['client_name'],
                'service' => $service,
                'ip' => $request->ip()
            ]);
            
            return $next($request);
            
        } catch (\Exception $e) {
            // 记录错误日志
            Log::error('CAS票据检查中间件错误', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ticket' => $this->maskTicket($request->get('ticket', '')),
                'service' => $request->get('service', ''),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return $this->handleValidationFailure(
                $request->get('ticket', ''),
                $request->get('service', ''),
                TicketValidationRecord::ERROR_INTERNAL_ERROR,
                '票据验证过程中发生内部错误',
                $request
            );
        }
    }
    
    /**
     * 验证票据
     * 
     * @param string $ticket 票据
     * @param string $service 服务URL
     * @param Request $request 请求对象
     * @return array 验证结果
     */
    protected function validateTicket(string $ticket, string $service, Request $request): array
    {
        // 确定票据类型
        $ticketType = $this->determineTicketType($ticket);
        
        switch ($ticketType) {
            case 'ST':
                return $this->validateServiceTicket($ticket, $service, $request);
            case 'PT':
                return $this->validateProxyTicket($ticket, $service, $request);
            default:
                return [
                    'valid' => false,
                    'error_code' => TicketValidationRecord::ERROR_INVALID_TICKET,
                    'error_message' => '无效的票据格式'
                ];
        }
    }
    
    /**
     * 确定票据类型
     * 
     * @param string $ticket
     * @return string
     */
    protected function determineTicketType(string $ticket): string
    {
        if (strpos($ticket, 'ST-') === 0) {
            return 'ST';
        } elseif (strpos($ticket, 'PT-') === 0) {
            return 'PT';
        }
        
        return 'UNKNOWN';
    }
    
    /**
     * 验证服务票据
     * 
     * @param string $ticket
     * @param string $service
     * @param Request $request
     * @return array
     */
    protected function validateServiceTicket(string $ticket, string $service, Request $request): array
    {
        // 查找ST记录
        $st = ServiceTicket::where('st', $ticket)->first();
        
        if (!$st) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_INVALID_TICKET,
                'error_message' => '票据不存在'
            ];
        }
        
        // 检查票据是否过期
        if ($st->isExpired()) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_EXPIRED_TICKET,
                'error_message' => '票据已过期'
            ];
        }
        
        // 检查票据是否已被消费
        if ($st->isConsumed()) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_CONSUMED_TICKET,
                'error_message' => '票据已被使用'
            ];
        }
        
        // 验证服务URL匹配
        if (!empty($service) && $st->service_url !== $service) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_INVALID_SERVICE,
                'error_message' => '服务URL不匹配'
            ];
        }
        
        // 验证IP地址（如果启用）
        if (config('casserver.validate_ip', true)) {
            $cachedData = $this->ticketManager->getTicketData($ticket);
            if ($cachedData && isset($cachedData['ip']) && $cachedData['ip'] !== $request->ip()) {
                return [
                    'valid' => false,
                    'error_code' => TicketValidationRecord::ERROR_INVALID_TICKET,
                    'error_message' => 'IP地址不匹配'
                ];
            }
        }
        
        // 消费票据（ST是一次性的）
        $st->consume();
        
        // 获取用户信息
        $user = $st->getUser();
        
        return [
            'valid' => true,
            'ticket_type' => TicketValidationRecord::TICKET_TYPE_ST,
            'user_id' => $user ? $user->id : null,
            'client_name' => $st->client_name,
            'service_url' => $st->service_url,
            'user_data' => $user ? $user->toArray() : null,
            'ticket_data' => $st->toArray()
        ];
    }
    
    /**
     * 验证代理票据
     * 
     * @param string $ticket
     * @param string $service
     * @param Request $request
     * @return array
     */
    protected function validateProxyTicket(string $ticket, string $service, Request $request): array
    {
        // 查找PT记录
        $pt = ProxyTicket::where('pt', $ticket)->first();
        
        if (!$pt) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_INVALID_TICKET,
                'error_message' => '代理票据不存在'
            ];
        }
        
        // 检查票据是否过期
        if ($pt->isExpired()) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_EXPIRED_TICKET,
                'error_message' => '代理票据已过期'
            ];
        }
        
        // 检查票据是否已被消费
        if ($pt->isConsumed()) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_CONSUMED_TICKET,
                'error_message' => '代理票据已被使用'
            ];
        }
        
        // 验证目标服务URL匹配
        if (!empty($service) && $pt->target_service !== $service) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_INVALID_SERVICE,
                'error_message' => '目标服务URL不匹配'
            ];
        }
        
        // 验证代理权限
        if (!$pt->validateTargetService()) {
            return [
                'valid' => false,
                'error_code' => TicketValidationRecord::ERROR_UNAUTHORIZED_PROXY,
                'error_message' => '未授权的代理访问'
            ];
        }
        
        // 消费票据（PT也是一次性的）
        $pt->consume();
        
        // 获取用户信息
        $user = $pt->getUser();
        
        return [
            'valid' => true,
            'ticket_type' => TicketValidationRecord::TICKET_TYPE_PT,
            'user_id' => $user ? $user->id : null,
            'client_name' => $pt->target_service, // PT的客户端是目标服务
            'service_url' => $pt->target_service,
            'proxy_chain' => $pt->getProxyChainArray(),
            'user_data' => $user ? $user->toArray() : null,
            'ticket_data' => $pt->toArray()
        ];
    }
    
    /**
     * 处理验证失败的情况
     * 
     * @param string $ticket
     * @param string $service
     * @param string $errorCode
     * @param string $errorMessage
     * @param Request $request
     * @return Response
     */
    protected function handleValidationFailure(
        string $ticket,
        string $service,
        string $errorCode,
        string $errorMessage,
        Request $request
    ): Response {
        // 确定票据类型
        $ticketType = $this->determineTicketType($ticket);
        
        // 尝试从票据或服务URL提取客户端名称
        $clientName = $this->extractClientNameFromService($service) ?: 'unknown';
        
        // 记录验证失败
        TicketValidationRecord::recordFailure(
            $ticket,
            $ticketType,
            $clientName,
            $errorCode,
            $errorMessage,
            $service,
            $request->all(),
            $request->ip(),
            $request->userAgent()
        );
        
        // 记录警告日志
        Log::warning('CAS票据验证失败', [
            'ticket' => $this->maskTicket($ticket),
            'service' => $service,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        // 返回JSON错误响应
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $errorCode,
                'message' => $errorMessage
            ],
            'timestamp' => now()->toISOString()
        ], 401);
    }
    
    /**
     * 从服务URL中提取客户端名称
     * 
     * @param string $serviceUrl
     * @return string|null
     */
    protected function extractClientNameFromService(string $serviceUrl): ?string
    {
        if (empty($serviceUrl)) {
            return null;
        }
        
        $parsedUrl = parse_url($serviceUrl);
        return isset($parsedUrl['host']) ? $parsedUrl['host'] : null;
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
