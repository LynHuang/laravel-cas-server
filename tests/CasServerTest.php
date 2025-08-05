<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use HuangFuLin\LaravelCasServer\Models\Client;
use HuangFuLin\LaravelCasServer\Models\TicketGrantingTicket;
use HuangFuLin\LaravelCasServer\Models\ServiceTicket;
use HuangFuLin\LaravelCasServer\Services\TicketManager;
use HuangFuLin\LaravelCasServer\Services\AuthService;
use HuangFuLin\LaravelCasServer\Repositories\ClientRepository;
use HuangFuLin\LaravelCasServer\Repositories\TicketRepository;

/**
 * CAS服务器功能测试类
 * 
 * 测试CAS服务器的核心功能，包括：
 * - 票据生成和验证
 * - 用户认证
 * - 单点登录流程
 * - 单点登出流程
 * - API接口
 */
class CasServerTest extends BaseTestCase
{
    use RefreshDatabase;
    
    protected $ticketManager;
    protected $authService;
    protected $clientRepository;
    protected $ticketRepository;
    protected $testUser;
    protected $testClient;
    
    /**
     * 设置测试环境
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // 配置测试数据库
        Config::set('database.default', 'testing');
        Config::set('database.connections.testing', TestConfig::getDatabaseConfig());
        
        // 配置CAS设置
        Config::set('cas', TestConfig::getCasConfig());
        
        // 运行迁移
        $this->artisan('migrate', ['--database' => 'testing']);
        
        // 初始化服务
        $this->ticketManager = app(TicketManager::class);
        $this->authService = app(AuthService::class);
        $this->clientRepository = app(ClientRepository::class);
        $this->ticketRepository = app(TicketRepository::class);
        
        // 创建测试数据
        $this->createTestData();
    }
    
    /**
     * 创建测试数据
     */
    protected function createTestData(): void
    {
        // 创建测试用户
        $userData = TestConfig::getTestUsers()[0];
        $this->testUser = User::create($userData);
        
        // 创建测试客户端
        $clientData = TestConfig::getTestClients()[0];
        $this->testClient = Client::create($clientData);
        
        // 创建其他测试客户端
        foreach (array_slice(TestConfig::getTestClients(), 1) as $client) {
            Client::create($client);
        }
    }
    
    /**
     * 测试票据生成功能
     */
    public function testTicketGeneration(): void
    {
        echo "\n=== 测试票据生成功能 ===\n";
        
        // 测试TGT生成
        $tgt = $this->ticketManager->createTGT($this->testUser, '127.0.0.1', 'Test User Agent');
        $this->assertNotNull($tgt);
        $this->assertStringStartsWith('TGT-', $tgt->ticket);
        echo "✓ TGT生成成功: {$tgt->ticket}\n";
        
        // 测试ST生成
        $st = $this->ticketManager->createST($tgt, $this->testClient->service_url);
        $this->assertNotNull($st);
        $this->assertStringStartsWith('ST-', $st->ticket);
        echo "✓ ST生成成功: {$st->ticket}\n";
        
        // 测试PT生成
        $pt = $this->ticketManager->createPT($tgt, $this->testClient->service_url, 'https://proxy.test.com');
        $this->assertNotNull($pt);
        $this->assertStringStartsWith('PT-', $pt->ticket);
        echo "✓ PT生成成功: {$pt->ticket}\n";
    }
    
    /**
     * 测试票据验证功能
     */
    public function testTicketValidation(): void
    {
        echo "\n=== 测试票据验证功能 ===\n";
        
        // 创建票据
        $tgt = $this->ticketManager->createTGT($this->testUser, '127.0.0.1', 'Test User Agent');
        $st = $this->ticketManager->createST($tgt, $this->testClient->service_url);
        
        // 测试ST验证
        $result = $this->ticketManager->validateST($st->ticket, $this->testClient->service_url);
        $this->assertTrue($result['success']);
        $this->assertEquals($this->testUser->id, $result['user']->id);
        echo "✓ ST验证成功\n";
        
        // 测试重复验证（应该失败）
        $result = $this->ticketManager->validateST($st->ticket, $this->testClient->service_url);
        $this->assertFalse($result['success']);
        echo "✓ ST重复验证正确拒绝\n";
        
        // 测试TGT验证
        $result = $this->ticketManager->validateTGT($tgt->ticket);
        $this->assertTrue($result['success']);
        $this->assertEquals($this->testUser->id, $result['user']->id);
        echo "✓ TGT验证成功\n";
    }
    
    /**
     * 测试用户认证功能
     */
    public function testUserAuthentication(): void
    {
        echo "\n=== 测试用户认证功能 ===\n";
        
        // 测试正确的用户名密码
        $result = $this->authService->authenticate('user1@test.com', 'password123');
        $this->assertTrue($result['success']);
        $this->assertEquals($this->testUser->id, $result['user']->id);
        echo "✓ 用户认证成功\n";
        
        // 测试错误的密码
        $result = $this->authService->authenticate('user1@test.com', 'wrongpassword');
        $this->assertFalse($result['success']);
        echo "✓ 错误密码正确拒绝\n";
        
        // 测试不存在的用户
        $result = $this->authService->authenticate('nonexistent@test.com', 'password123');
        $this->assertFalse($result['success']);
        echo "✓ 不存在用户正确拒绝\n";
    }
    
    /**
     * 测试客户端验证功能
     */
    public function testClientValidation(): void
    {
        echo "\n=== 测试客户端验证功能 ===\n";
        
        // 测试有效的客户端
        $client = $this->clientRepository->findByServiceUrl($this->testClient->service_url);
        $this->assertNotNull($client);
        $this->assertTrue($client->enabled);
        echo "✓ 有效客户端验证成功\n";
        
        // 测试禁用的客户端
        $disabledClient = $this->clientRepository->findByServiceUrl('https://disabled.test.com');
        $this->assertNotNull($disabledClient);
        $this->assertFalse($disabledClient->enabled);
        echo "✓ 禁用客户端状态正确\n";
        
        // 测试不存在的客户端
        $nonexistentClient = $this->clientRepository->findByServiceUrl('https://nonexistent.test.com');
        $this->assertNull($nonexistentClient);
        echo "✓ 不存在客户端正确返回null\n";
    }
    
    /**
     * 测试单点登录流程
     */
    public function testSingleSignOnFlow(): void
    {
        echo "\n=== 测试单点登录流程 ===\n";
        
        // 1. 用户首次访问应用，重定向到CAS
        echo "1. 用户访问应用，重定向到CAS登录页面\n";
        
        // 2. 用户在CAS登录
        $authResult = $this->authService->authenticate('user1@test.com', 'password123');
        $this->assertTrue($authResult['success']);
        echo "2. 用户在CAS成功登录\n";
        
        // 3. 创建TGT
        $tgt = $this->ticketManager->createTGT($authResult['user'], '127.0.0.1', 'Test Browser');
        $this->assertNotNull($tgt);
        echo "3. 创建TGT: {$tgt->ticket}\n";
        
        // 4. 创建ST并重定向回应用
        $st = $this->ticketManager->createST($tgt, $this->testClient->service_url);
        $this->assertNotNull($st);
        echo "4. 创建ST并重定向: {$st->ticket}\n";
        
        // 5. 应用验证ST
        $validateResult = $this->ticketManager->validateST($st->ticket, $this->testClient->service_url);
        $this->assertTrue($validateResult['success']);
        echo "5. 应用验证ST成功，用户登录完成\n";
        
        // 6. 用户访问第二个应用（已有TGT）
        $secondClient = Client::where('client_id', 'test_app_2')->first();
        $st2 = $this->ticketManager->createST($tgt, $secondClient->service_url);
        $this->assertNotNull($st2);
        echo "6. 为第二个应用创建ST: {$st2->ticket}\n";
        
        // 7. 第二个应用验证ST
        $validateResult2 = $this->ticketManager->validateST($st2->ticket, $secondClient->service_url);
        $this->assertTrue($validateResult2['success']);
        echo "7. 第二个应用验证ST成功，实现单点登录\n";
    }
    
    /**
     * 测试单点登出流程
     */
    public function testSingleLogoutFlow(): void
    {
        echo "\n=== 测试单点登出流程 ===\n";
        
        // 准备：创建登录会话
        $tgt = $this->ticketManager->createTGT($this->testUser, '127.0.0.1', 'Test Browser');
        $st1 = $this->ticketManager->createST($tgt, $this->testClient->service_url);
        $this->ticketManager->validateST($st1->ticket, $this->testClient->service_url);
        
        $secondClient = Client::where('client_id', 'test_app_2')->first();
        $st2 = $this->ticketManager->createST($tgt, $secondClient->service_url);
        $this->ticketManager->validateST($st2->ticket, $secondClient->service_url);
        
        echo "准备：用户已登录两个应用\n";
        
        // 执行单点登出
        $logoutResult = $this->ticketManager->performSingleLogout($tgt->ticket, 'user_logout');
        $this->assertTrue($logoutResult['success']);
        echo "✓ 单点登出执行成功\n";
        
        // 验证TGT已失效
        $tgtValidation = $this->ticketManager->validateTGT($tgt->ticket);
        $this->assertFalse($tgtValidation['success']);
        echo "✓ TGT已失效\n";
        
        // 验证相关ST已清理
        $stCount = ServiceTicket::where('tgt_id', $tgt->id)->count();
        $this->assertEquals(0, $stCount);
        echo "✓ 相关ST已清理\n";
    }
    
    /**
     * 测试票据过期功能
     */
    public function testTicketExpiration(): void
    {
        echo "\n=== 测试票据过期功能 ===\n";
        
        // 创建过期的TGT
        $tgt = $this->ticketManager->createTGT($this->testUser, '127.0.0.1', 'Test Browser');
        
        // 手动设置过期时间
        $tgt->update(['expires_at' => now()->subMinutes(10)]);
        
        // 验证过期TGT
        $result = $this->ticketManager->validateTGT($tgt->ticket);
        $this->assertFalse($result['success']);
        $this->assertEquals('INVALID_TICKET', $result['error_code']);
        echo "✓ 过期TGT验证正确失败\n";
        
        // 测试清理过期票据
        $cleanupResult = $this->ticketManager->cleanupExpiredTickets();
        $this->assertGreaterThanOrEqual(0, $cleanupResult['cleaned_count']);
        echo "✓ 过期票据清理完成，清理数量: {$cleanupResult['cleaned_count']}\n";
    }
    
    /**
     * 运行所有测试
     */
    public function runAllTests(): void
    {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "开始运行Laravel CAS服务器功能测试\n";
        echo str_repeat('=', 60) . "\n";
        
        try {
            $this->testTicketGeneration();
            $this->testTicketValidation();
            $this->testUserAuthentication();
            $this->testClientValidation();
            $this->testSingleSignOnFlow();
            $this->testSingleLogoutFlow();
            $this->testTicketExpiration();
            
            echo "\n" . str_repeat('=', 60) . "\n";
            echo "✅ 所有测试通过！CAS服务器功能正常\n";
            echo str_repeat('=', 60) . "\n";
            
        } catch (\Exception $e) {
            echo "\n" . str_repeat('=', 60) . "\n";
            echo "❌ 测试失败: " . $e->getMessage() . "\n";
            echo "错误位置: " . $e->getFile() . ":" . $e->getLine() . "\n";
            echo str_repeat('=', 60) . "\n";
            throw $e;
        }
    }
    
    /**
     * 显示测试统计信息
     */
    public function showTestStatistics(): void
    {
        echo "\n=== 测试统计信息 ===\n";
        
        $userCount = User::count();
        $clientCount = Client::count();
        $tgtCount = TicketGrantingTicket::count();
        $stCount = ServiceTicket::count();
        
        echo "用户数量: {$userCount}\n";
        echo "客户端数量: {$clientCount}\n";
        echo "TGT数量: {$tgtCount}\n";
        echo "ST数量: {$stCount}\n";
        
        // 显示活跃票据
        $activeTgtCount = TicketGrantingTicket::where('expires_at', '>', now())->count();
        $activeStCount = ServiceTicket::where('expires_at', '>', now())->count();
        
        echo "活跃TGT数量: {$activeTgtCount}\n";
        echo "活跃ST数量: {$activeStCount}\n";
    }
}