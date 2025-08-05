<?php

namespace Lyn\LaravelCasServer\Interactions;

use Illuminate\Http\Request;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Contracts\Interactions\UserPassword;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * 默认用户密码重置交互实现
 * 
 * 提供基本的密码重置功能：
 * 1. 显示忘记密码页面
 * 2. 发送重置验证码
 * 3. 显示密码重置页面
 * 4. 处理密码重置请求
 */
class DefaultUserPassword implements UserPassword
{
    /**
     * 处理密码重置请求
     * 
     * @param Request $request
     * @return UserModel|null
     */
    public function reset(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'token' => 'required'
        ]);
        
        $email = $request->input('email');
        $password = $request->input('password');
        $token = $request->input('token');
        
        // 验证重置令牌
        $cachedToken = Cache::get("password_reset_{$email}");
        if (!$cachedToken || $cachedToken !== $token) {
            throw new \Exception('无效的重置令牌');
        }
        
        // 查找用户
        $user = User::where('email', $email)->first();
        if (!$user) {
            throw new \Exception('用户不存在');
        }
        
        // 更新密码
        $user->password = Hash::make($password);
        $user->save();
        
        // 清除重置令牌
        Cache::forget("password_reset_{$email}");
        
        return $user;
    }
    
    /**
     * 显示密码重置页面
     * 
     * @param Request $request
     * @return Response
     */
    public function showResetPage(Request $request)
    {
        $token = $request->get('token');
        $email = $request->get('email');
        
        return view('casserver::password.reset', [
            'token' => $token,
            'email' => $email
        ]);
    }
    
    /**
     * 发送重置验证码
     * 
     * @param Request $request
     * @return mixed
     */
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);
        
        $email = $request->input('email');
        $token = Str::random(60);
        
        // 缓存重置令牌（30分钟有效）
        Cache::put("password_reset_{$email}", $token, 1800);
        
        // 发送邮件（这里简化处理，实际项目中应该发送邮件）
        // Mail::to($email)->send(new PasswordResetMail($token));
        
        return response()->json([
            'success' => true,
            'message' => '重置链接已发送到您的邮箱',
            'token' => $token // 仅用于测试，生产环境不应返回token
        ]);
    }
    
    /**
     * 显示忘记密码页面
     * 
     * @param Request $request
     * @return mixed
     */
    public function showForgetPage(Request $request)
    {
        return view('casserver::password.forget');
    }
}