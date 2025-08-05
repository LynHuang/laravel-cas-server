<?php

namespace Lyn\LaravelCasServer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Lyn\LaravelCasServer\Contracts\Interactions\UserPassword;
use Lyn\LaravelCasServer\Services\AuthService;
use Lyn\LaravelCasServer\Repositories\ClientRepository;

/**
 * CAS密码管理控制器
 * 
 * 处理密码相关功能：
 * 1. 密码重置页面显示
 * 2. 密码重置处理
 * 3. 忘记密码页面
 * 4. 发送重置验证码
 * 5. 验证重置令牌
 * 6. 密码强度验证
 */
class PasswordController extends Controller
{
    /**
     * 密码交互接口
     * 
     * @var UserPassword
     */
    protected $passwordInteraction;
    
    /**
     * 认证服务
     * 
     * @var AuthService
     */
    protected $authService;
    
    /**
     * 客户端仓储
     * 
     * @var ClientRepository
     */
    protected $clientRepository;

    /**
     * 构造函数
     * 
     * @param UserPassword $passwordInteraction 密码交互接口
     * @param AuthService $authService 认证服务
     * @param ClientRepository $clientRepository 客户端仓储
     */
    public function __construct(
        UserPassword $passwordInteraction,
        AuthService $authService,
        ClientRepository $clientRepository
    ) {
        $this->passwordInteraction = $passwordInteraction;
        $this->authService = $authService;
        $this->clientRepository = $clientRepository;
    }

    /**
     * 显示密码重置页面
     * 
     * 用户通过重置链接访问此页面来重置密码
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function passwordGetReset(Request $request)
    {
        try {
            $token = $request->get('token');
            $email = $request->get('email');
            
            // 验证必需参数
            if (empty($token) || empty($email)) {
                Log::warning('密码重置页面访问缺少参数', [
                    'token_provided' => !empty($token),
                    'email_provided' => !empty($email),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);
                
                return $this->passwordInteraction->showResetPage($request)
                    ->withErrors(['token' => '重置链接无效或已过期']);
            }
            
            // 验证重置令牌
            $user = $this->getUserByResetToken($token, $email);
            
            if (!$user) {
                Log::warning('无效的密码重置令牌', [
                    'email' => $email,
                    'token' => $this->maskToken($token),
                    'ip' => $request->ip()
                ]);
                
                return $this->passwordInteraction->showResetPage($request)
                    ->withErrors(['token' => '重置链接无效或已过期']);
            }
            
            // 记录重置页面访问
            Log::info('密码重置页面访问', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);
            
            return $this->passwordInteraction->showResetPage($request);
            
        } catch (\Exception $e) {
            Log::error('显示密码重置页面错误', [
                'error' => $e->getMessage(),
                'email' => $request->get('email', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->passwordInteraction->showResetPage($request)
                ->withErrors(['error' => '系统错误，请稍后重试']);
        }
    }

    /**
     * 处理密码重置
     * 
     * 验证重置令牌并更新用户密码
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function passwordPostReset(Request $request)
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => ['required', 'confirmed', PasswordRule::defaults()],
            ], [
                'token.required' => '重置令牌不能为空',
                'email.required' => '邮箱地址不能为空',
                'email.email' => '邮箱地址格式不正确',
                'password.required' => '新密码不能为空',
                'password.confirmed' => '两次输入的密码不一致',
                'password.min' => '密码长度至少为8位',
            ]);
            
            if ($validator->fails()) {
                return $this->passwordInteraction->showResetPage($request)
                    ->withErrors($validator)
                    ->withInput($request->except('password', 'password_confirmation'));
            }
            
            $token = $request->get('token');
            $email = $request->get('email');
            $password = $request->get('password');
            
            // 速率限制检查
            $rateLimitKey = 'password-reset:' . $request->ip();
            if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                
                Log::warning('密码重置速率限制触发', [
                    'ip' => $request->ip(),
                    'email' => $email,
                    'retry_after' => $seconds
                ]);
                
                return $this->passwordInteraction->showResetPage($request)
                    ->withErrors(['email' => "请求过于频繁，请在 {$seconds} 秒后重试"])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
            
            // 验证重置令牌并获取用户
            $user = $this->getUserByResetToken($token, $email);
            
            if (!$user) {
                RateLimiter::hit($rateLimitKey, 300); // 5分钟限制
                
                Log::warning('密码重置失败：无效令牌', [
                    'email' => $email,
                    'token' => $this->maskToken($token),
                    'ip' => $request->ip()
                ]);
                
                return $this->passwordInteraction->showResetPage($request)
                    ->withErrors(['token' => '重置链接无效或已过期'])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
            
            // 更新密码
            $user->password = Hash::make($password);
            $user->save();
            
            // 删除重置令牌
            Password::deleteToken($user);
            
            // 清除速率限制
            RateLimiter::clear($rateLimitKey);
            
            Log::info('密码重置成功', [
                'user_id' => $user->id,
                'email' => $email,
                'ip' => $request->ip()
            ]);
            
            // 重定向到登录页面
            $redirectUrl = route('cas.login') . '?message=' . urlencode('密码重置成功，请使用新密码登录');
            
            return redirect($redirectUrl);
            
        } catch (\Exception $e) {
            Log::error('密码重置处理错误', [
                'error' => $e->getMessage(),
                'email' => $request->get('email', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->passwordInteraction->showResetPage($request)
                ->withErrors(['error' => '系统错误，请稍后重试'])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }

    /**
     * 显示忘记密码页面
     * 
     * 用户可以在此页面输入邮箱来请求密码重置
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function passwordForget(Request $request)
    {
        try {
            // 获取来源服务参数
            $service = $request->get('service', '');
            $clientName = $this->extractClientNameFromService($service);
            
            // 验证客户端（如果提供）
            if ($clientName && !$this->clientRepository->isClientEnabled($clientName)) {
                Log::warning('忘记密码页面访问：无效客户端', [
                    'client_name' => $clientName,
                    'service' => $service,
                    'ip' => $request->ip()
                ]);
            }
            
            return $this->passwordInteraction->showForgetPage($request);
            
        } catch (\Exception $e) {
            Log::error('显示忘记密码页面错误', [
                'error' => $e->getMessage(),
                'service' => $request->get('service', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->passwordInteraction->showForgetPage($request)
                ->withErrors(['error' => '系统错误，请稍后重试']);
        }
    }

    /**
     * 发送密码重置验证码
     * 
     * 向用户邮箱发送密码重置链接
     *
     * @param Request $request HTTP请求
     * @return Response
     */
    public function passwordSendCode(Request $request)
    {
        try {
            // 验证请求参数
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
            ], [
                'email.required' => '邮箱地址不能为空',
                'email.email' => '邮箱地址格式不正确',
                'email.exists' => '该邮箱地址未注册',
            ]);
            
            if ($validator->fails()) {
                return $this->passwordInteraction->showForgetPage($request)
                    ->withErrors($validator)
                    ->withInput();
            }
            
            $email = $request->get('email');
            
            // 速率限制检查
            $rateLimitKey = 'password-reset-send:' . $email;
            if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                
                Log::warning('发送密码重置邮件速率限制触发', [
                    'email' => $email,
                    'ip' => $request->ip(),
                    'retry_after' => $seconds
                ]);
                
                return $this->passwordInteraction->showForgetPage($request)
                    ->withErrors(['email' => "发送过于频繁，请在 {$seconds} 秒后重试"])
                    ->withInput();
            }
            
            // 查找用户
            $user = User::where('email', $email)->first();
            
            if (!$user) {
                RateLimiter::hit($rateLimitKey, 300); // 5分钟限制
                
                Log::warning('密码重置请求：用户不存在', [
                    'email' => $email,
                    'ip' => $request->ip()
                ]);
                
                // 为了安全，不暴露用户是否存在
                return $this->passwordInteraction->showForgetPage($request)
                    ->with('status', '如果该邮箱已注册，您将收到密码重置链接');
            }
            
            // 发送重置链接
            $status = Password::sendResetLink(['email' => $email]);
            
            if ($status === Password::RESET_LINK_SENT) {
                RateLimiter::hit($rateLimitKey, 300); // 成功发送后也要限制
                
                Log::info('密码重置邮件发送成功', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'ip' => $request->ip()
                ]);
                
                return $this->passwordInteraction->showForgetPage($request)
                    ->with('status', '密码重置链接已发送到您的邮箱');
            } else {
                RateLimiter::hit($rateLimitKey, 60); // 失败时短时间限制
                
                Log::error('密码重置邮件发送失败', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'status' => $status,
                    'ip' => $request->ip()
                ]);
                
                return $this->passwordInteraction->showForgetPage($request)
                    ->withErrors(['email' => '发送重置链接失败，请稍后重试'])
                    ->withInput();
            }
            
        } catch (\Exception $e) {
            Log::error('发送密码重置验证码错误', [
                'error' => $e->getMessage(),
                'email' => $request->get('email', ''),
                'ip' => $request->ip()
            ]);
            
            return $this->passwordInteraction->showForgetPage($request)
                ->withErrors(['error' => '系统错误，请稍后重试'])
                ->withInput();
        }
    }
    
    /**
     * 验证密码强度
     * 
     * AJAX接口，用于实时验证密码强度
     *
     * @param Request $request HTTP请求
     * @return Response JSON响应
     */
    public function validatePasswordStrength(Request $request)
    {
        try {
            $password = $request->get('password', '');
            
            if (empty($password)) {
                return response()->json([
                    'valid' => false,
                    'score' => 0,
                    'message' => '密码不能为空'
                ]);
            }
            
            $score = $this->calculatePasswordStrength($password);
            $message = $this->getPasswordStrengthMessage($score);
            
            return response()->json([
                'valid' => $score >= 3,
                'score' => $score,
                'message' => $message
            ]);
            
        } catch (\Exception $e) {
            Log::error('密码强度验证错误', [
                'error' => $e->getMessage(),
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'valid' => false,
                'score' => 0,
                'message' => '验证失败'
            ], 500);
        }
    }
    
    /**
     * 根据重置令牌获取用户
     * 
     * @param string $token 重置令牌
     * @param string $email 邮箱地址
     * @return User|null
     */
    protected function getUserByResetToken(string $token, string $email): ?User
    {
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return null;
        }
        
        // 验证令牌
        if (!Password::tokenExists($user, $token)) {
            return null;
        }
        
        return $user;
    }
    
    /**
     * 从服务URL中提取客户端名称
     * 
     * @param string $service 服务URL
     * @return string|null
     */
    protected function extractClientNameFromService(string $service): ?string
    {
        if (empty($service)) {
            return null;
        }
        
        $parsedUrl = parse_url($service);
        
        if (!$parsedUrl || empty($parsedUrl['host'])) {
            return null;
        }
        
        return $parsedUrl['host'];
    }
    
    /**
     * 计算密码强度分数
     * 
     * @param string $password 密码
     * @return int 强度分数 (0-5)
     */
    protected function calculatePasswordStrength(string $password): int
    {
        $score = 0;
        
        // 长度检查
        if (strlen($password) >= 8) $score++;
        if (strlen($password) >= 12) $score++;
        
        // 字符类型检查
        if (preg_match('/[a-z]/', $password)) $score++; // 小写字母
        if (preg_match('/[A-Z]/', $password)) $score++; // 大写字母
        if (preg_match('/[0-9]/', $password)) $score++; // 数字
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++; // 特殊字符
        
        // 最大分数为5
        return min($score, 5);
    }
    
    /**
     * 获取密码强度消息
     * 
     * @param int $score 强度分数
     * @return string
     */
    protected function getPasswordStrengthMessage(int $score): string
    {
        switch ($score) {
            case 0:
            case 1:
                return '密码强度：很弱';
            case 2:
                return '密码强度：弱';
            case 3:
                return '密码强度：中等';
            case 4:
                return '密码强度：强';
            case 5:
                return '密码强度：很强';
            default:
                return '密码强度：未知';
        }
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
