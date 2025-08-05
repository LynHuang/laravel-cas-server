<?php
return [
    /**
     * 路由配置
     */
    'route' => [
        'domain' => null,
        'prefix' => 'cas',
        'middleware' => 'web'
    ],

    /**
     * 启用CAS SSO模块
     */
    'enable' => env('CAS_ENABLE', true),

    /**
     * 响应格式配置
     */
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

    /**
     * 用户配置
     */
    'user' => [
        /**
         * 用户模型类
         * 可以自定义用户模型，必须实现CasUserInterface接口
         */
        'model' => env('CAS_USER_MODEL', App\Models\User::class),

        /**
         * 用户表名
         */
        'table' => env('CAS_USER_TABLE', 'users'),

        /**
         * 用户ID字段名
         */
        'id' => env('CAS_USER_ID_FIELD', 'id'),

        /**
         * 用户名字段（用于登录）
         */
        'username_field' => env('CAS_USERNAME_FIELD', 'email'),

        /**
         * 密码字段
         */
        'password_field' => env('CAS_PASSWORD_FIELD', 'password'),

        /**
         * 单点登出控制
         */
        'cas_slo' => env('CAS_SLO_ENABLED', true),

        /**
         * 返回给客户端的用户信息字段
         * 可以是数组或者回调函数名
         */
        'user_info' => [
            'id', 'email', 'name', 'created_at'
        ],

        /**
         * 自定义用户信息处理器
         * 如果设置了此项，将使用自定义处理器来格式化用户信息
         * 格式: 'ClassName@methodName' 或 callable
         */
        'custom_user_info_handler' => env('CAS_CUSTOM_USER_INFO_HANDLER', null),
    ],

    /**
     * 票据配置
     */
    'ticket' => [
        /**
         * 票据有效期（秒）
         */
        'expire' => env('CAS_TICKET_EXPIRE', 60 * 5),

        /**
         * TGT有效期（秒）
         */
        'tgt_expire' => env('CAS_TGT_EXPIRE', 60 * 60 * 2), // 2小时

        /**
         * 票据前缀配置
         */
        'prefix' => [
            'st' => env('CAS_ST_PREFIX', 'ST-'),
            'tgt' => env('CAS_TGT_PREFIX', 'TGT-'),
            'pgt' => env('CAS_PGT_PREFIX', 'PGT-'),
            'pt' => env('CAS_PT_PREFIX', 'PT-'),
        ],
    ],

    /**
     * 交互接口配置
     */
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

    /**
     * 视图模板配置
     */
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

    /**
     * 安全配置
     */
    'security' => [
        /**
         * SSL验证
         */
        'verify_ssl' => env('CAS_VERIFY_SSL', false),

        /**
         * 允许的客户端域名（为空则不限制）
         */
        'allowed_domains' => env('CAS_ALLOWED_DOMAINS', null),

        /**
         * 登录失败最大尝试次数
         */
        'max_login_attempts' => env('CAS_MAX_LOGIN_ATTEMPTS', 5),

        /**
         * 登录失败锁定时间（分钟）
         */
        'lockout_time' => env('CAS_LOCKOUT_TIME', 15),
    ],

    /**
     * 日志配置
     */
    'logging' => [
        /**
         * 是否启用日志
         */
        'enabled' => env('CAS_LOGGING_ENABLED', true),

        /**
         * 日志频道
         */
        'channel' => env('CAS_LOG_CHANNEL', 'default'),

        /**
         * 记录的事件类型
         */
        'events' => [
            'login' => true,
            'logout' => true,
            'ticket_validation' => true,
            'errors' => true,
        ],
    ],
];
