<?php

namespace Demo;

/**
 * Laravel CAS服务器使用演示
 * 
 * 这个演示展示了如何在Laravel项目中集成和使用CAS服务器扩展包
 */
class CasServerDemo
{
    /**
     * 演示CAS服务器的基本配置
     */
    public static function showBasicConfiguration(): void
    {
        echo "\n=== CAS服务器基本配置演示 ===\n";
        
        $config = [
            // config/cas.php 配置示例
            'server_name' => 'My CAS Server',
            'server_url' => 'https://cas.example.com',
            
            // 路由配置
            'route' => [
                'domain' => null,
                'prefix' => 'cas',
                'middleware' => ['web'],
            ],
            
            // 票据配置
            'ticket' => [
                'tgt_lifetime' => 7200,  // TGT有效期2小时
                'st_lifetime' => 300,    // ST有效期5分钟
                'pt_lifetime' => 300,    // PT有效期5分钟
                'pgt_lifetime' => 7200,  // PGT有效期2小时
            ],
            
            // 单点登出配置
            'logout' => [
                'enabled' => true,
                'timeout' => 10,
                'allowed_domains' => [
                    'app1.example.com',
                    'app2.example.com',
                ],
            ],
            
            // 用户配置
            'user' => [
                'model' => 'App\\Models\\User',
                'cas_slo' => true,
            ],
            
            // 调试配置
            'debug' => env('APP_DEBUG', false),
            'log_level' => 'info',
        ];
        
        echo "配置文件示例 (config/cas.php):\n";
        echo "<?php\n\nreturn " . var_export($config, true) . ";\n";
    }
    
    /**
     * 演示如何注册CAS客户端
     */
    public static function showClientRegistration(): void
    {
        echo "\n=== CAS客户端注册演示 ===\n";
        
        echo "1. 使用数据库迁移创建客户端:\n";
        echo "```php\n";
        echo "// database/migrations/xxxx_create_cas_clients.php\n";
        echo "Schema::create('cas_clients', function (Blueprint \$table) {\n";
        echo "    \$table->id();\n";
        echo "    \$table->string('client_name');\n";
        echo "    \$table->string('client_id')->unique();\n";
        echo "    \$table->string('client_secret');\n";
        echo "    \$table->string('service_url');\n";
        echo "    \$table->string('logout_url')->nullable();\n";
        echo "    \$table->boolean('enabled')->default(true);\n";
        echo "    \$table->timestamps();\n";
        echo "});\n";
        echo "```\n\n";
        
        echo "2. 使用Seeder添加客户端数据:\n";
        echo "```php\n";
        echo "// database/seeders/CasClientSeeder.php\n";
        echo "use HuangFuLin\\LaravelCasServer\\Models\\Client;\n\n";
        echo "Client::create([\n";
        echo "    'client_name' => 'My Application',\n";
        echo "    'client_id' => 'my_app',\n";
        echo "    'client_secret' => 'secret123',\n";
        echo "    'service_url' => 'https://app.example.com',\n";
        echo "    'logout_url' => 'https://app.example.com/logout',\n";
        echo "    'enabled' => true,\n";
        echo "]);\n";
        echo "```\n";
    }
    
    /**
     * 演示CAS认证流程
     */
    public static function showAuthenticationFlow(): void
    {
        echo "\n=== CAS认证流程演示 ===\n";
        
        echo "1. 用户访问受保护的应用:\n";
        echo "   GET https://app.example.com/protected\n\n";
        
        echo "2. 应用检测用户未登录，重定向到CAS:\n";
        echo "   302 https://cas.example.com/cas/login?service=https://app.example.com\n\n";
        
        echo "3. 用户在CAS登录页面输入凭据:\n";
        echo "   POST https://cas.example.com/cas/login\n";
        echo "   Content-Type: application/x-www-form-urlencoded\n";
        echo "   \n";
        echo "   username=user@example.com&password=secret&service=https://app.example.com\n\n";
        
        echo "4. CAS验证凭据成功，创建TGT和ST，重定向回应用:\n";
        echo "   302 https://app.example.com?ticket=ST-1-abc123def456\n\n";
        
        echo "5. 应用验证ST获取用户信息:\n";
        echo "   GET https://cas.example.com/cas/serviceValidate?service=https://app.example.com&ticket=ST-1-abc123def456\n\n";
        
        echo "6. CAS返回验证结果:\n";
        echo "   ```xml\n";
        echo "   <cas:serviceResponse xmlns:cas='http://www.yale.edu/tp/cas'>\n";
        echo "       <cas:authenticationSuccess>\n";
        echo "           <cas:user>user@example.com</cas:user>\n";
        echo "           <cas:attributes>\n";
        echo "               <cas:name>John Doe</cas:name>\n";
        echo "               <cas:email>user@example.com</cas:email>\n";
        echo "           </cas:attributes>\n";
        echo "       </cas:authenticationSuccess>\n";
        echo "   </cas:serviceResponse>\n";
        echo "   ```\n";
    }
    
    /**
     * 演示如何在Laravel控制器中使用CAS中间件
     */
    public static function showMiddlewareUsage(): void
    {
        echo "\n=== CAS中间件使用演示 ===\n";
        
        echo "1. 在路由中使用CAS认证中间件:\n";
        echo "```php\n";
        echo "// routes/web.php\n";
        echo "Route::group(['middleware' => 'cas.auth'], function () {\n";
        echo "    Route::get('/dashboard', 'DashboardController@index');\n";
        echo "    Route::get('/profile', 'ProfileController@show');\n";
        echo "});\n";
        echo "```\n\n";
        
        echo "2. 在控制器构造函数中使用:\n";
        echo "```php\n";
        echo "// app/Http/Controllers/DashboardController.php\n";
        echo "class DashboardController extends Controller\n";
        echo "{\n";
        echo "    public function __construct()\n";
        echo "    {\n";
        echo "        \$this->middleware('cas.auth');\n";
        echo "    }\n";
        echo "    \n";
        echo "    public function index()\n";
        echo "    {\n";
        echo "        \$user = auth()->user();\n";
        echo "        return view('dashboard', compact('user'));\n";
        echo "    }\n";
        echo "}\n";
        echo "```\n";
    }
    
    /**
     * 演示单点登出实现
     */
    public static function showSingleLogoutImplementation(): void
    {
        echo "\n=== 单点登出实现演示 ===\n";
        
        echo "1. 在应用中添加登出链接:\n";
        echo "```html\n";
        echo "<!-- resources/views/layouts/app.blade.php -->\n";
        echo "<a href=\"{{ route('cas.logout', ['service' => url('/')]) }}\">\n";
        echo "    登出\n";
        echo "</a>\n";
        echo "```\n\n";
        
        echo "2. 处理CAS登出回调（可选）:\n";
        echo "```php\n";
        echo "// app/Http/Controllers/Auth/LogoutController.php\n";
        echo "class LogoutController extends Controller\n";
        echo "{\n";
        echo "    public function casLogout(Request \$request)\n";
        echo "    {\n";
        echo "        // 处理来自CAS的登出通知\n";
        echo "        \$logoutRequest = \$request->input('logoutRequest');\n";
        echo "        \n";
        echo "        if (\$logoutRequest) {\n";
        echo "            // 解析SAML登出请求\n";
        echo "            \$sessionIndex = \$this->parseLogoutRequest(\$logoutRequest);\n";
        echo "            \n";
        echo "            // 清理本地会话\n";
        echo "            \$this->cleanupLocalSession(\$sessionIndex);\n";
        echo "        }\n";
        echo "        \n";
        echo "        return response('OK', 200);\n";
        echo "    }\n";
        echo "}\n";
        echo "```\n";
    }
    
    /**
     * 演示API接口使用
     */
    public static function showApiUsage(): void
    {
        echo "\n=== API接口使用演示 ===\n";
        
        echo "1. 验证单个票据:\n";
        echo "```bash\n";
        echo "curl -X POST https://cas.example.com/cas/api/v1/validate \\\n";
        echo "  -H \"Content-Type: application/json\" \\\n";
        echo "  -d '{\n";
        echo "    \"token\": \"ST-1-abc123def456\",\n";
        echo "    \"service\": \"https://app.example.com\"\n";
        echo "  }'\n";
        echo "```\n\n";
        
        echo "2. 批量验证票据:\n";
        echo "```bash\n";
        echo "curl -X POST https://cas.example.com/cas/api/v1/batch-validate \\\n";
        echo "  -H \"Content-Type: application/json\" \\\n";
        echo "  -d '{\n";
        echo "    \"tokens\": [\n";
        echo "      {\n";
        echo "        \"token\": \"ST-1-abc123def456\",\n";
        echo "        \"service\": \"https://app1.example.com\"\n";
        echo "      },\n";
        echo "      {\n";
        echo "        \"token\": \"ST-2-def456ghi789\",\n";
        echo "        \"service\": \"https://app2.example.com\"\n";
        echo "      }\n";
        echo "    ]\n";
        echo "  }'\n";
        echo "```\n\n";
        
        echo "3. 根据票据获取用户信息:\n";
        echo "```bash\n";
        echo "curl -X GET \"https://cas.example.com/cas/api/v1/user?ticket=TGT-1-abc123def456\"\n";
        echo "```\n";
    }
    
    /**
     * 演示事件监听
     */
    public static function showEventListening(): void
    {
        echo "\n=== 事件监听演示 ===\n";
        
        echo "1. 监听用户登录事件:\n";
        echo "```php\n";
        echo "// app/Listeners/CasUserLoginListener.php\n";
        echo "use HuangFuLin\\LaravelCasServer\\Events\\CasUserLoggedInEvent;\n\n";
        echo "class CasUserLoginListener\n";
        echo "{\n";
        echo "    public function handle(CasUserLoggedInEvent \$event)\n";
        echo "    {\n";
        echo "        \$user = \$event->getUser();\n";
        echo "        \$tgt = \$event->getTgt();\n";
        echo "        \$clientIp = \$event->getClientIp();\n";
        echo "        \n";
        echo "        // 记录登录日志\n";
        echo "        Log::info('User logged in via CAS', [\n";
        echo "            'user_id' => \$user->id,\n";
        echo "            'tgt' => \$tgt,\n";
        echo "            'ip' => \$clientIp,\n";
        echo "        ]);\n";
        echo "        \n";
        echo "        // 更新用户最后登录时间\n";
        echo "        \$user->update(['last_login_at' => now()]);\n";
        echo "    }\n";
        echo "}\n";
        echo "```\n\n";
        
        echo "2. 监听用户登出事件:\n";
        echo "```php\n";
        echo "// app/Listeners/CasUserLogoutListener.php\n";
        echo "use HuangFuLin\\LaravelCasServer\\Events\\CasUserLoggedOutEvent;\n\n";
        echo "class CasUserLogoutListener\n";
        echo "{\n";
        echo "    public function handle(CasUserLoggedOutEvent \$event)\n";
        echo "    {\n";
        echo "        \$user = \$event->getUser();\n";
        echo "        \$reason = \$event->getReason();\n";
        echo "        \n";
        echo "        // 记录登出日志\n";
        echo "        Log::info('User logged out from CAS', [\n";
        echo "            'user_id' => \$user->id,\n";
        echo "            'reason' => \$reason,\n";
        echo "        ]);\n";
        echo "        \n";
        echo "        // 清理用户相关缓存\n";
        echo "        Cache::forget(\"user_permissions_{\$user->id}\");\n";
        echo "    }\n";
        echo "}\n";
        echo "```\n";
    }
    
    /**
     * 演示自定义用户认证
     */
    public static function showCustomAuthentication(): void
    {
        echo "\n=== 自定义用户认证演示 ===\n";
        
        echo "1. 创建自定义认证服务:\n";
        echo "```php\n";
        echo "// app/Services/CustomAuthService.php\n";
        echo "use HuangFuLin\\LaravelCasServer\\Contracts\\AuthServiceInterface;\n\n";
        echo "class CustomAuthService implements AuthServiceInterface\n";
        echo "{\n";
        echo "    public function authenticate(string \$username, string \$password): array\n";
        echo "    {\n";
        echo "        // 自定义认证逻辑\n";
        echo "        // 例如：LDAP认证、第三方API认证等\n";
        echo "        \n";
        echo "        if (\$this->validateWithLdap(\$username, \$password)) {\n";
        echo "            \$user = \$this->findOrCreateUser(\$username);\n";
        echo "            \n";
        echo "            return [\n";
        echo "                'success' => true,\n";
        echo "                'user' => \$user,\n";
        echo "                'message' => 'Authentication successful'\n";
        echo "            ];\n";
        echo "        }\n";
        echo "        \n";
        echo "        return [\n";
        echo "            'success' => false,\n";
        echo "            'user' => null,\n";
        echo "            'message' => 'Invalid credentials'\n";
        echo "        ];\n";
        echo "    }\n";
        echo "}\n";
        echo "```\n\n";
        
        echo "2. 在服务提供者中注册自定义服务:\n";
        echo "```php\n";
        echo "// app/Providers/AppServiceProvider.php\n";
        echo "public function register()\n";
        echo "{\n";
        echo "    \$this->app->bind(\n";
        echo "        \\HuangFuLin\\LaravelCasServer\\Contracts\\AuthServiceInterface::class,\n";
        echo "        \\App\\Services\\CustomAuthService::class\n";
        echo "    );\n";
        echo "}\n";
        echo "```\n";
    }
    
    /**
     * 运行所有演示
     */
    public static function runAllDemos(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "Laravel CAS服务器扩展包 - 使用演示\n";
        echo str_repeat('=', 80) . "\n";
        
        self::showBasicConfiguration();
        self::showClientRegistration();
        self::showAuthenticationFlow();
        self::showMiddlewareUsage();
        self::showSingleLogoutImplementation();
        self::showApiUsage();
        self::showEventListening();
        self::showCustomAuthentication();
        
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "演示完成！\n";
        echo "\n更多信息请参考：\n";
        echo "- README.md: 详细的安装和配置说明\n";
        echo "- CAS_PROTOCOL_GUIDE.md: CAS协议详细说明\n";
        echo "- 源代码注释: 每个类和方法都有详细的中文注释\n";
        echo str_repeat('=', 80) . "\n";
    }
}