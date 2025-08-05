<?php

namespace Tests;

/**
 * 测试配置类
 * 
 * 提供测试环境的配置信息
 */
class TestConfig
{
    /**
     * 获取测试数据库配置
     * 
     * @return array
     */
    public static function getDatabaseConfig(): array
    {
        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }
    
    /**
     * 获取测试CAS配置
     * 
     * @return array
     */
    public static function getCasConfig(): array
    {
        return [
            'server_name' => 'Test CAS Server',
            'server_url' => 'https://cas.test.com',
            'route' => [
                'domain' => null,
                'prefix' => 'cas',
                'middleware' => ['web'],
            ],
            'ticket' => [
                'tgt_lifetime' => 7200,
                'st_lifetime' => 300,
                'pt_lifetime' => 300,
                'pgt_lifetime' => 7200,
            ],
            'logout' => [
                'enabled' => true,
                'timeout' => 10,
                'allowed_domains' => ['test.com', 'example.com'],
            ],
            'user' => [
                'cas_slo' => true,
            ],
            'debug' => true,
            'log_level' => 'debug',
        ];
    }
    
    /**
     * 获取测试用户数据
     * 
     * @return array
     */
    public static function getTestUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Test User 1',
                'email' => 'user1@test.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Test User 2',
                'email' => 'user2@test.com',
                'password' => bcrypt('password456'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }
    
    /**
     * 获取测试客户端数据
     * 
     * @return array
     */
    public static function getTestClients(): array
    {
        return [
            [
                'id' => 1,
                'client_name' => 'Test App 1',
                'client_id' => 'test_app_1',
                'client_secret' => 'secret123',
                'service_url' => 'https://app1.test.com',
                'logout_url' => 'https://app1.test.com/logout',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'client_name' => 'Test App 2',
                'client_id' => 'test_app_2',
                'client_secret' => 'secret456',
                'service_url' => 'https://app2.test.com',
                'logout_url' => 'https://app2.test.com/logout',
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'client_name' => 'Disabled App',
                'client_id' => 'disabled_app',
                'client_secret' => 'secret789',
                'service_url' => 'https://disabled.test.com',
                'logout_url' => 'https://disabled.test.com/logout',
                'enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];
    }
}