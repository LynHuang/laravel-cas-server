<?php

namespace Lyn\LaravelCasServer\Interactions;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Contracts\Interactions\UserRegister;
use Symfony\Component\HttpFoundation\Response;

/**
 * 默认用户注册实现
 * 
 * 提供基础的用户注册功能，包括：
 * 1. 显示注册页面
 * 2. 处理用户注册请求
 * 3. 验证注册数据
 * 4. 创建新用户
 */
class DefaultUserRegister implements UserRegister
{
    /**
     * 处理用户注册
     *
     * @param Request $request HTTP请求
     * @return UserModel|null 注册成功返回用户模型，失败返回null
     */
    public function register(Request $request)
    {
        // 验证注册数据
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'name.required' => '姓名不能为空',
            'name.max' => '姓名长度不能超过255个字符',
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '该邮箱已被注册',
            'password.required' => '密码不能为空',
            'password.min' => '密码长度至少8个字符',
            'password.confirmed' => '两次输入的密码不一致',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            // 获取用户模型类
            $userModelClass = config('casserver.user_model', '\\App\\Models\\User');
            
            // 创建新用户
            $user = new $userModelClass();
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->password = Hash::make($request->input('password'));
            $user->email_verified_at = now(); // 默认邮箱已验证
            $user->save();

            return $user;
        } catch (\Exception $e) {
            \Log::error('用户注册失败', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip()
            ]);
            
            return redirect()->back()
                ->withErrors(['register' => '注册失败，请稍后重试'])
                ->withInput();
        }
    }

    /**
     * 显示注册页面
     *
     * @param Request $request HTTP请求
     * @return Response 注册页面响应
     */
    public function showRegisterPage(Request $request)
    {
        // 检查是否启用注册功能
        if (!config('casserver.enable_registration', false)) {
            abort(404, '注册功能未启用');
        }

        $data = [
            'service' => $request->get('service', ''),
            'from' => $request->get('from', ''),
            'errors' => session('errors'),
            'old' => session('_old_input', []),
        ];

        // 检查是否有自定义注册视图
        if (view()->exists('casserver.register')) {
            return response()->view('casserver.register', $data);
        }

        // 使用默认注册页面模板
        return response()->view('casserver::register', $data);
    }
}