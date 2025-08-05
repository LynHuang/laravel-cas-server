<?php

namespace Lyn\LaravelCasServer\Interactions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 默认用户登录交互实现
 * 
 * 提供CAS协议的默认登录交互逻辑：
 * 1. 显示登录页面
 * 2. 处理用户登录
 * 3. 显示错误页面
 * 4. 处理用户登出
 * 5. 支持自定义视图模板
 */
class DefaultUserLogin implements UserLogin
{
    /**
     * 从请求中获取用户凭据并进行登录
     *
     * @param Request $request
     * @return UserModel|null
     */
    public function login(Request $request)
    {
        $username = $request->get('username', '');
        $password = $request->get('password', '');
        
        if (empty($username) || empty($password)) {
            return null;
        }
        
        // 获取用户名字段配置
        $usernameField = config('casserver.user.username_field', 'email');
        
        // 尝试登录
        $credentials = [
            $usernameField => $username,
            'password' => $password
        ];
        
        if (Auth::attempt($credentials)) {
            return Auth::user();
        }
        
        return null;
    }

    /**
     * 显示错误页面
     * 
     * @param CasException $exception
     * @return Response
     */
    public function showErrorPage(CasException $exception)
    {
        $template = $this->getViewTemplate('error');
        
        return response()->view($template, [
            'title' => '认证错误',
            'message' => $exception->getMessage(),
            'error_code' => $exception->getCode(),
            'exception' => $exception
        ], 400);
    }

    /**
     * 显示登录页面
     *
     * @param Request $request
     * @param array $errors
     * @return Response
     */
    public function showLoginPage(Request $request, array $errors = [])
    {
        $template = $this->getViewTemplate('login');
        
        $data = [
            'service' => $request->get('service', ''),
            'renew' => $request->get('renew', false),
            'gateway' => $request->get('gateway', false),
            'errors' => $errors,
            'old_input' => $request->old()
        ];
        
        return response()->view($template, $data);
    }

    /**
     * 执行登出逻辑（清除会话/cookie等）
     *
     * @param Request $request
     * @return Response
     */
    public function logout(Request $request)
    {
        // 清除Laravel认证会话
        Auth::logout();
        
        // 清除会话数据
        Session::flush();
        
        // 重新生成会话ID
        Session::regenerate();
        
        $template = $this->getViewTemplate('logout');
        
        return response()->view($template, [
            'message' => '您已成功登出',
            'redirect_url' => $request->get('service', '')
        ]);
    }
    
    /**
     * 获取视图模板名称
     * 
     * @param string $type 模板类型
     * @return string
     */
    protected function getViewTemplate(string $type): string
    {
        // 检查是否启用自定义视图
        if (config('casserver.views.custom_enabled', false)) {
            $customPath = config('casserver.views.custom_path', 'cas');
            return $customPath . '.' . $type;
        }
        
        // 使用配置中的模板映射
        $templates = config('casserver.views.templates', []);
        
        if (isset($templates[$type])) {
            return $templates[$type];
        }
        
        // 默认模板
        return 'casserver::' . $type;
    }
}