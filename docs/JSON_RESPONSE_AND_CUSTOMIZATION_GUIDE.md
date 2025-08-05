# Laravel CAS服务器 - JSON响应格式与自定义配置指南

## 概述

本指南介绍Laravel CAS服务器扩展包的最新功能：

1. **JSON响应格式支持** - 将传统的XML响应格式改为现代化的JSON格式
2. **自定义用户模型** - 支持使用自定义用户模型
3. **自定义用户信息字段** - 灵活配置返回给客户端的用户信息
4. **自定义Blade模板** - 支持自定义登录、登出等页面模板
5. **自定义交互接口** - 支持自定义用户登录、注册等交互逻辑

## 1. JSON响应格式

### 1.1 配置

在 `config/casserver.php` 中配置响应格式：

```php
'response' => [
    /**
     * 默认响应格式: json 或 xml
     * json: 现代化的JSON格式响应
     * xml: 标准CAS协议XML格式响应
     */
    'default_format' => env('CAS_RESPONSE_FORMAT', 'json'),
    
    /**
     * 是否同时支持两种格式（通过Accept头或format参数判断）
     */
    'support_both' => env('CAS_SUPPORT_BOTH_FORMATS', true),
],
```

### 1.2 JSON响应格式示例

#### 成功验证响应

```json
{
    "serviceResponse": {
        "authenticationSuccess": {
            "user": "user@example.com",
            "user_data": {
                "id": 1,
                "email": "user@example.com",
                "name": "张三",
                "created_at": "2023-01-01T00:00:00Z"
            },
            "user_attributes": {
                "department": "技术部",
                "role": "开发工程师",
                "permissions": ["read", "write"]
            }
        }
    }
}
```

#### 验证失败响应

```json
{
    "serviceResponse": {
        "authenticationFailure": {
            "code": "INVALID_TICKET",
            "description": "票据无效或已过期"
        }
    }
}
```

### 1.3 兼容性

- 默认使用JSON格式，保持向后兼容
- 客户端可以通过 `format=xml` 参数请求XML格式
- 支持通过 `Accept` 头指定响应格式

## 2. 自定义用户模型

### 2.1 配置自定义用户模型

```php
'user' => [
    /**
     * 用户模型类
     * 可以自定义用户模型，必须实现CasUserInterface接口
     */
    'model' => env('CAS_USER_MODEL', App\Models\User::class),
    
    /**
     * 用户名字段（用于登录）
     */
    'username_field' => env('CAS_USERNAME_FIELD', 'email'),
    
    /**
     * 密码字段
     */
    'password_field' => env('CAS_PASSWORD_FIELD', 'password'),
],
```

### 2.2 创建自定义用户模型

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Leo108\CAS\Contracts\Models\UserModel;

class CustomUser extends Authenticatable implements UserModel
{
    protected $table = 'custom_users';
    
    protected $fillable = [
        'username', 'email', 'password', 'department', 'role'
    ];
    
    // 实现UserModel接口的方法
    public function getCASIdentifier()
    {
        return $this->username;
    }
    
    public function getCASAttributes()
    {
        return [
            'email' => $this->email,
            'department' => $this->department,
            'role' => $this->role,
        ];
    }
}
```

## 3. 自定义用户信息字段

### 3.1 配置用户信息字段

```php
'user' => [
    /**
     * 返回给客户端的用户信息字段
     * 可以是数组或者回调函数名
     */
    'user_info' => [
        'id', 'email', 'name', 'department', 'role', 'created_at'
    ],
    
    /**
     * 自定义用户信息处理器
     * 如果设置了此项，将使用自定义处理器来格式化用户信息
     * 格式: 'ClassName@methodName' 或 callable
     */
    'custom_user_info_handler' => env('CAS_CUSTOM_USER_INFO_HANDLER', null),
],
```

### 3.2 创建自定义用户信息处理器

```php
<?php

namespace App\Services;

class CustomUserInfoHandler
{
    public function handle($user)
    {
        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'profile' => [
                'name' => $user->name,
                'avatar' => $user->avatar_url,
                'department' => $user->department,
                'position' => $user->position,
            ],
            'permissions' => $user->permissions->pluck('name')->toArray(),
            'last_login' => $user->last_login_at?->toISOString(),
        ];
    }
}
```

然后在配置中设置：

```php
'custom_user_info_handler' => 'App\\Services\\CustomUserInfoHandler@handle',
```

## 4. 自定义Blade模板

### 4.1 配置自定义视图

```php
'views' => [
    /**
     * 是否使用自定义视图
     */
    'custom_enabled' => env('CAS_CUSTOM_VIEWS', false),
    
    /**
     * 自定义视图路径
     */
    'custom_path' => env('CAS_CUSTOM_VIEWS_PATH', 'cas'),
    
    /**
     * 视图模板映射
     */
    'templates' => [
        'login' => env('CAS_LOGIN_TEMPLATE', 'casserver::login'),
        'logout' => env('CAS_LOGOUT_TEMPLATE', 'casserver::logout'),
        'error' => env('CAS_ERROR_TEMPLATE', 'casserver::error'),
        'password_forget' => env('CAS_PASSWORD_FORGET_TEMPLATE', 'casserver::password.forget'),
        'password_reset' => env('CAS_PASSWORD_RESET_TEMPLATE', 'casserver::password.reset'),
    ],
],
```

### 4.2 创建自定义模板

创建 `resources/views/cas/login.blade.php`：

```blade
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAS登录 - 我的应用</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    登录到您的账户
                </h2>
            </div>
            <form class="mt-8 space-y-6" method="POST">
                @csrf
                <input type="hidden" name="service" value="{{ $service }}">
                
                <div class="rounded-md shadow-sm -space-y-px">
                    <div>
                        <input type="email" name="username" required
                               class="relative block w-full px-3 py-2 border border-gray-300 rounded-t-md"
                               placeholder="邮箱地址">
                    </div>
                    <div>
                        <input type="password" name="password" required
                               class="relative block w-full px-3 py-2 border border-gray-300 rounded-b-md"
                               placeholder="密码">
                    </div>
                </div>
                
                @if($errors->any())
                    <div class="text-red-600 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif
                
                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        登录
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
```

## 5. 自定义交互接口

### 5.1 配置自定义交互类

```php
'interactions' => [
    /**
     * 自定义用户登录交互类
     * 必须实现 Lyn\LaravelCasServer\Contracts\Interactions\UserLogin 接口
     */
    'user_login' => env('CAS_USER_LOGIN_CLASS', null),
    
    /**
     * 自定义用户注册交互类
     * 必须实现 Lyn\LaravelCasServer\Contracts\Interactions\UserRegister 接口
     */
    'user_register' => env('CAS_USER_REGISTER_CLASS', null),
    
    /**
     * 自定义密码重置交互类
     * 必须实现 Lyn\LaravelCasServer\Contracts\Interactions\UserPassword 接口
     */
    'user_password' => env('CAS_USER_PASSWORD_CLASS', null),
],
```

### 5.2 创建自定义登录交互类

```php
<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Leo108\CAS\Contracts\Models\UserModel;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Symfony\Component\HttpFoundation\Response;

class CustomUserLogin implements UserLogin
{
    public function login(Request $request): ?UserModel
    {
        $credentials = $request->only('username', 'password');
        
        // 添加自定义登录逻辑
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // 记录登录日志
            \Log::info('用户登录成功', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return $user;
        }
        
        return null;
    }
    
    public function showLoginPage(Request $request, array $errors = []): Response
    {
        return response()->view('custom.login', [
            'service' => $request->get('service'),
            'errors' => $errors,
        ]);
    }
    
    public function showErrorPage(CasException $exception): Response
    {
        return response()->view('custom.error', [
            'error' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]);
    }
    
    public function logout(Request $request): Response
    {
        Auth::logout();
        
        return response()->view('custom.logout', [
            'message' => '您已成功登出',
        ]);
    }
}
```

### 5.3 注册自定义交互类

在 `AppServiceProvider` 中注册：

```php
public function register()
{
    // 注册自定义登录交互
    $this->app->bind(
        \Lyn\LaravelCasServer\Contracts\Interactions\UserLogin::class,
        \App\Services\CustomUserLogin::class
    );
}
```

或者在配置文件中设置：

```php
'user_login' => App\Services\CustomUserLogin::class,
```

## 6. 环境变量配置

在 `.env` 文件中添加以下配置：

```env
# CAS响应格式
CAS_RESPONSE_FORMAT=json
CAS_SUPPORT_BOTH_FORMATS=true

# 自定义用户模型
CAS_USER_MODEL=App\Models\User
CAS_USERNAME_FIELD=email
CAS_PASSWORD_FIELD=password

# 自定义用户信息处理器
CAS_CUSTOM_USER_INFO_HANDLER=App\Services\CustomUserInfoHandler@handle

# 自定义视图
CAS_CUSTOM_VIEWS=true
CAS_CUSTOM_VIEWS_PATH=cas
CAS_LOGIN_TEMPLATE=cas.login
CAS_LOGOUT_TEMPLATE=cas.logout
CAS_ERROR_TEMPLATE=cas.error

# 自定义交互类
CAS_USER_LOGIN_CLASS=App\Services\CustomUserLogin
```

## 7. 迁移指南

### 7.1 从XML格式迁移到JSON格式

1. 更新配置文件中的 `default_format` 为 `json`
2. 客户端应用需要更新解析逻辑以支持JSON格式
3. 如需保持兼容性，设置 `support_both` 为 `true`

### 7.2 客户端适配

客户端需要更新票据验证逻辑：

```php
// 旧的XML解析方式
$xml = simplexml_load_string($response);
$user = (string)$xml->authenticationSuccess->user;

// 新的JSON解析方式
$data = json_decode($response, true);
$user = $data['serviceResponse']['authenticationSuccess']['user'];
$userData = $data['serviceResponse']['authenticationSuccess']['user_data'];
```

## 8. 最佳实践

1. **安全性**：自定义用户信息处理器中避免返回敏感信息
2. **性能**：合理配置用户信息字段，避免返回过多数据
3. **兼容性**：在生产环境中逐步迁移，保持向后兼容
4. **日志**：在自定义交互类中添加适当的日志记录
5. **测试**：充分测试自定义功能，确保稳定性

## 9. 故障排除

### 9.1 常见问题

**Q: JSON响应格式不生效？**
A: 检查配置文件中的 `default_format` 设置，确保为 `json`

**Q: 自定义用户模型不工作？**
A: 确保自定义模型实现了 `UserModel` 接口

**Q: 自定义视图不显示？**
A: 检查 `custom_enabled` 配置和视图文件路径

### 9.2 调试技巧

1. 启用调试模式：`APP_DEBUG=true`
2. 查看日志文件：`storage/logs/laravel.log`
3. 使用 `dd()` 函数调试变量
4. 检查配置缓存：`php artisan config:clear`

## 10. 更新日志

### v2.0.0
- 添加JSON响应格式支持
- 支持自定义用户模型
- 支持自定义用户信息字段
- 支持自定义Blade模板
- 支持自定义交互接口
- 改进配置文件结构
- 增强错误处理

---

更多信息请参考 [Laravel CAS服务器文档](README.md)