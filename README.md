# Laravel CAS Server 扩展包

一个功能完整的 Laravel CAS (Central Authentication Service) 服务器实现，支持 CAS 1.0、2.0 和 3.0 协议，提供现代化的 JSON 响应格式和丰富的自定义选项。

## 功能特性

### 🔐 完整的 CAS 协议支持
- **CAS 1.0**: 基础的票据验证
- **CAS 2.0**: 支持 XML 和 JSON 格式响应，支持代理认证
- **CAS 3.0**: 扩展属性支持，增强的代理功能
- **现代化 JSON 响应**: 可配置的 JSON 格式响应，更适合现代 Web 应用

### 🎨 丰富的自定义选项
- **自定义用户模型**: 支持自定义用户模型和认证字段
- **自定义用户信息**: 可配置返回的用户信息字段和处理器
- **自定义模板**: 支持 Blade 模板自定义，包括登录、登出和错误页面
- **自定义交互接口**: 支持自定义用户登录、注册和密码重置逻辑

### 🎫 票据管理
- **TGT (Ticket Granting Ticket)**: 票据授予票据，用于 SSO 会话管理
- **ST (Service Ticket)**: 服务票据，用于服务认证
- **PT (Proxy Ticket)**: 代理票据，支持代理认证
- **PGT (Proxy Granting Ticket)**: 代理授予票据

### 🔄 单点登录 (SSO) 和单点登出 (SLO)
- 完整的单点登录流程
- 支持单点登出，自动通知所有已登录的客户端应用
- 会话管理和清理

### 🛡️ 安全特性
- 票据过期管理
- 客户端白名单验证
- 安全的密码重置功能
- 详细的审计日志

### 📊 管理功能
- 客户端管理
- 票据统计
- 系统监控
- API 接口

## 项目结构

```
laravel-cas-server/
├── src/                          # 核心源代码
│   ├── CasServerServiceProvider.php # 服务提供者
│   ├── Http/                     # HTTP 相关
│   │   ├── Controllers/          # 控制器
│   │   ├── Middleware/           # 中间件
│   │   └── Requests/             # 请求验证
│   ├── Models/                   # 数据模型
│   ├── Services/                 # 业务逻辑服务
│   ├── Events/                   # 事件类
│   ├── Listeners/                # 事件监听器
│   ├── Contracts/                # 接口定义
│   ├── Migrations/               # 数据库迁移文件
│   ├── Routes/                   # 路由定义
│   ├── config/                   # 配置文件
│   └── resources/                # 资源文件
│       └── views/                # Blade 模板
├── resources/                    # 项目资源文件
│   └── views/                    # 自定义视图模板
├── composer.json                 # Composer 依赖配置
├── README.md                     # 项目说明文档
├── .gitignore                    # Git 忽略文件配置
└── LICENSE                       # 许可证文件
```

### 核心目录说明

- **src/**: 扩展包的核心代码，包含所有 CAS 服务器功能实现
- **src/Http/Controllers/**: CAS 协议相关的控制器，处理登录、登出、票据验证等
- **src/Services/**: 核心业务逻辑，包括票据管理、用户认证、客户端管理等
- **src/Models/**: 数据模型，包括票据、客户端、会话等
- **src/Migrations/**: 数据库迁移文件，用于创建 CAS 相关数据表
- **src/Routes/**: 路由定义文件，定义 CAS 协议的各种端点
- **src/config/**: 配置文件模板，包含 CAS 服务器的各项配置选项
- **resources/views/**: 可自定义的 Blade 模板文件

## 安装

### 1. 通过 Composer 安装

```bash
composer require lyn/laravel-cas-server
```

### 2. 发布配置文件

```bash
php artisan vendor:publish --provider="Lyn\LaravelCasServer\CasServerServiceProvider"
```

### 3. 运行数据库迁移

```bash
php artisan migrate
```

### 4. 创建 CAS 客户端

```bash
php artisan cas:create-client
```

## 配置

### 基础配置

编辑 `config/casserver.php` 文件：

```php
return [
    // 服务器基础配置
    'server_name' => env('CAS_SERVER_NAME', 'CAS Server'),
    'server_url' => env('CAS_SERVER_URL', 'https://cas.example.com'),
    
    // 响应格式配置
    'response' => [
        'default_format' => env('CAS_RESPONSE_FORMAT', 'json'), // 'xml' 或 'json'
    ],
    
    // 路由配置
    'route' => [
        'domain' => env('CAS_ROUTE_DOMAIN'),
        'prefix' => env('CAS_ROUTE_PREFIX', 'cas'),
        'middleware' => ['web'],
    ],
    
    // 用户模型配置
    'user' => [
        'model' => env('CAS_USER_MODEL', App\Models\User::class),
        'username_field' => env('CAS_USERNAME_FIELD', 'email'),
        'custom_fields' => [
            'name', 'email', 'roles', // 自定义返回字段
        ],
        'custom_handler' => null, // 自定义用户信息处理器类
    ],
    
    // 票据配置
    'ticket' => [
        'tgt_lifetime' => 7200, // TGT 生命周期（秒）
        'st_lifetime' => 300,   // ST 生命周期（秒）
        'pt_lifetime' => 300,   // PT 生命周期（秒）
        'pgt_lifetime' => 7200, // PGT 生命周期（秒）
    ],
    
    // 视图模板配置
    'views' => [
        'custom_enabled' => env('CAS_CUSTOM_VIEWS', false),
        'custom_path' => env('CAS_CUSTOM_VIEW_PATH', 'cas'),
        'templates' => [
            'login' => 'casserver::login',
            'logout' => 'casserver::logout', 
            'error' => 'casserver::error',
        ],
    ],
    
    // 自定义交互接口配置
    'interactions' => [
        'user_login' => env('CAS_USER_LOGIN_CLASS', null),
        'user_register' => env('CAS_USER_REGISTER_CLASS', null),
        'user_password' => env('CAS_USER_PASSWORD_CLASS', null),
    ],
    
    // 单点登出配置
    'logout' => [
        'enabled' => true,
        'timeout' => 10, // 登出请求超时时间
        'allowed_domains' => [], // 允许的登出重定向域名
    ],
];
```

## CAS 协议流程详解

### 1. 单点登录 (SSO) 流程

```
用户 -> 客户端应用 -> CAS 服务器 -> 认证 -> 返回票据 -> 客户端验证票据 -> 登录成功
```

#### 详细步骤：

1. **用户访问受保护资源**
   - 用户访问客户端应用的受保护页面
   - 客户端检测到用户未登录

2. **重定向到 CAS 服务器**
   ```
   https://cas.example.com/cas/login?service=https://app.example.com/callback
   ```

3. **用户认证**
   - 如果用户未登录，显示登录页面
   - 如果用户已登录且有有效 TGT，跳过登录

4. **生成服务票据 (ST)**
   - 认证成功后，CAS 服务器生成 ST
   - 重定向回客户端应用
   ```
   https://app.example.com/callback?ticket=ST-1-abc123...
   ```

5. **客户端验证票据**
   - 客户端应用调用 CAS 服务器验证 ST
   ```
   https://cas.example.com/cas/serviceValidate?service=https://app.example.com/callback&ticket=ST-1-abc123...
   ```

6. **返回用户信息**
   
   **XML 格式响应 (传统)**:
   ```xml
   <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>
       <cas:authenticationSuccess>
           <cas:user>username</cas:user>
           <cas:attributes>
               <cas:email>user@example.com</cas:email>
               <cas:name>User Name</cas:name>
           </cas:attributes>
       </cas:authenticationSuccess>
   </cas:serviceResponse>
   ```
   
   **JSON 格式响应 (推荐)**:
   ```json
   {
       "serviceResponse": {
           "authenticationSuccess": {
               "user": "username",
               "attributes": {
                   "email": "user@example.com",
                   "name": "User Name",
                   "roles": ["user", "admin"]
               }
           }
       }
   }
   ```

### 2. 单点登出 (SLO) 流程

```
用户登出 -> CAS 服务器 -> 通知所有客户端 -> 清理会话 -> 登出完成
```

#### 详细步骤：

1. **用户发起登出**
   ```
   https://cas.example.com/cas/logout?service=https://app.example.com
   ```

2. **CAS 服务器处理登出**
   - 查找用户的所有活跃会话
   - 生成登出请求

3. **通知客户端应用**
   - 向所有已登录的客户端发送 SAML 登出请求
   ```xml
   <samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol">
       <saml:NameID>username</saml:NameID>
       <samlp:SessionIndex>session_id</samlp:SessionIndex>
   </samlp:LogoutRequest>
   ```

4. **清理服务器会话**
   - 删除 TGT 和相关票据
   - 清理缓存和会话数据

5. **重定向用户**
   - 重定向到指定的 service URL 或默认页面

### 3. 代理认证流程

代理认证允许一个应用代表用户访问另一个应用。

1. **获取代理授予票据 (PGT)**
   - 在 ST 验证时请求 PGT
   ```
   https://cas.example.com/cas/serviceValidate?service=...&ticket=...&pgtUrl=https://app.example.com/pgtCallback
   ```

2. **生成代理票据 (PT)**
   ```
   https://cas.example.com/cas/proxy?pgt=PGT-1-abc123...&targetService=https://api.example.com
   ```

3. **使用 PT 访问目标服务**
   ```
   https://api.example.com/resource?ticket=PT-1-def456...
   ```

## API 接口

### 认证接口

#### 登录
```
GET /cas/login?service={service_url}
POST /cas/login
```

#### 登出
```
GET /cas/logout?service={service_url}
```

#### 票据验证
```
# CAS 1.0
GET /cas/validate?service={service}&ticket={ticket}

# CAS 2.0
GET /cas/serviceValidate?service={service}&ticket={ticket}
GET /cas/proxyValidate?service={service}&ticket={ticket}

# CAS 3.0
GET /cas/p3/serviceValidate?service={service}&ticket={ticket}
GET /cas/p3/proxyValidate?service={service}&ticket={ticket}
```

#### 代理票据
```
GET /cas/proxy?pgt={pgt}&targetService={target_service}
```

### 管理接口

#### 统计信息
```
GET /cas/admin/stats
```

#### 客户端管理
```
GET /cas/admin/clients
POST /cas/admin/clients/{id}/toggle
```

#### 清理过期票据
```
POST /cas/admin/cleanup
```

### API 接口

#### 票据验证
```
POST /cas/api/v1/validate
{
    "token": "ST-1-abc123..."
}
```

#### 批量验证
```
POST /cas/api/v1/batch-validate
{
    "tokens": ["ST-1-abc123...", "PT-1-def456..."]
}
```

#### 用户信息
```
GET /cas/api/v1/user?ticket={ticket}
```

## 中间件使用

### CAS 认证中间件

```php
// 在路由中使用
Route::middleware('cas_auth')->group(function () {
    Route::get('/protected', 'ProtectedController@index');
});

// 在控制器中使用
class ProtectedController extends Controller
{
    public function __construct()
    {
        $this->middleware('cas_auth');
    }
}
```

### 票据检查中间件

```php
Route::middleware('cas_ticket_check')->group(function () {
    Route::get('/api/data', 'ApiController@getData');
});
```

## 事件系统

### 监听 CAS 事件

```php
// 在 EventServiceProvider 中注册
protected $listen = [
    \Lyn\LaravelCasServer\Events\CasUserLoggedOutEvent::class => [
        \App\Listeners\UserLoggedOutListener::class,
    ],
];

// 创建监听器
class UserLoggedOutListener
{
    public function handle(CasUserLoggedOutEvent $event)
    {
        // 处理用户登出事件
        Log::info('User logged out', [
            'user_id' => $event->getUserId(),
            'reason' => $event->reason,
        ]);
    }
}
```

### 可用事件

- `CasUserLoggedOutEvent`: 用户登出事件
- `CasLogoutEvent`: CAS 单点登出事件

## 自定义配置

### 环境变量配置

在 `.env` 文件中配置以下变量：

```env
# 响应格式配置
CAS_RESPONSE_FORMAT=json

# 用户模型配置
CAS_USER_MODEL=App\Models\User
CAS_USERNAME_FIELD=email

# 自定义视图配置
CAS_CUSTOM_VIEWS=true
CAS_CUSTOM_VIEW_PATH=custom.cas

# 自定义交互类配置
CAS_USER_LOGIN_CLASS=App\Services\CustomUserLogin
CAS_USER_REGISTER_CLASS=App\Services\CustomUserRegister
CAS_USER_PASSWORD_CLASS=App\Services\CustomUserPassword
```

### 自定义用户模型

创建自定义用户模型：

```php
use Illuminate\Foundation\Auth\User as Authenticatable;

class CustomUser extends Authenticatable
{
    protected $fillable = [
        'username', 'email', 'password', 'name', 'roles'
    ];
    
    protected $casts = [
        'roles' => 'array',
    ];
    
    // 自定义用户信息获取方法
    public function getCasAttributes()
    {
        return [
            'email' => $this->email,
            'name' => $this->name,
            'roles' => $this->roles ?? [],
            'department' => $this->department,
        ];
    }
}
```

### 自定义用户信息处理器

创建自定义用户信息处理器：

```php
class CustomUserInfoHandler
{
    public function handle($user)
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'name' => $user->name,
            'roles' => $user->roles ?? [],
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'last_login' => $user->last_login_at?->toISOString(),
        ];
    }
}
```

### 自定义用户认证

实现 `UserLogin` 接口来自定义用户认证逻辑：

```php
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;

class CustomUserLogin implements UserLogin
{
    public function showLoginPage(Request $request)
    {
        return view('custom.cas.login');
    }
    
    public function login(Request $request)
    {
        // 自定义登录逻辑
        $credentials = $request->only('email', 'password');
        
        if (Auth::attempt($credentials)) {
            return Auth::user();
        }
        
        return false;
    }
    
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
```

### 自定义 Blade 模板

创建自定义登录模板 `resources/views/custom/cas/login.blade.php`：

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">{{ __('CAS Login') }}</div>
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    
                    <form method="POST" action="{{ route('cas.login') }}">
                        @csrf
                        <input type="hidden" name="service" value="{{ request('service') }}">
                        
                        <div class="form-group mb-3">
                            <label for="email">{{ __('Email') }}</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        
                        <div class="form-group mb-3">
                            <label for="password">{{ __('Password') }}</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            {{ __('Login') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```
```

## 安全建议

### 1. HTTPS 配置
- 生产环境必须使用 HTTPS
- 配置正确的 SSL 证书

### 2. 客户端验证
- 严格验证客户端 service URL
- 使用白名单机制

### 3. 票据安全
- 设置合理的票据过期时间
- 定期清理过期票据

### 4. 日志监控
- 启用详细的审计日志
- 监控异常登录行为

### 5. 会话管理
- 配置安全的会话设置
- 实施会话超时机制

## 故障排除

### 常见问题

#### 1. 票据验证失败
```
检查项：
- service URL 是否正确
- 票据是否过期
- 客户端是否已注册
```

#### 2. 单点登出不工作
```
检查项：
- 客户端是否配置了 logout_url
- 网络连接是否正常
- 事件监听器是否正确注册
```

#### 3. 代理认证失败
```
检查项：
- PGT URL 是否可访问
- 代理链是否正确
- 目标服务是否支持代理认证
```

### 调试模式

启用详细日志：

```php
// config/casserver.php
'debug' => env('CAS_DEBUG', false),
'log_level' => env('CAS_LOG_LEVEL', 'info'),
```

## 性能优化

### 1. 缓存配置
```php
// 使用 Redis 缓存
'cache' => [
    'driver' => 'redis',
    'prefix' => 'cas:',
    'ttl' => 3600,
],
```

### 2. 数据库优化
- 为票据表添加适当的索引
- 定期清理过期数据

### 3. 队列处理
```php
// 使用队列处理单点登出
'logout' => [
    'use_queue' => true,
    'queue_name' => 'cas-logout',
],
```

## 贡献

欢迎提交 Issue 和 Pull Request！

### 开发环境设置

1. 克隆仓库
2. 安装依赖：`composer install`
3. 运行测试：`vendor/bin/phpunit`

## 许可证

MIT License

## 更新日志

### v2.0.0 (最新)
- 🎉 **新增 JSON 响应格式支持**：可配置的 JSON 格式响应，更适合现代 Web 应用
- 🎨 **丰富的自定义选项**：支持自定义用户模型、用户信息字段、Blade 模板
- 🔧 **自定义交互接口**：支持自定义用户登录、注册和密码重置逻辑
- 📝 **增强配置文件**：新增多项可配置选项，提高灵活性
- 🧪 **完整测试覆盖**：包含 JSON 响应格式和自定义功能的测试
- 📚 **详细文档**：提供完整的配置和使用指南

### v1.0.0
- 初始版本发布
- 支持 CAS 1.0/2.0/3.0 协议
- 完整的 SSO/SLO 功能
- 管理界面和 API

---

如有问题，请查看 [Wiki](https://github.com/your-repo/wiki) 或提交 [Issue](https://github.com/your-repo/issues)。