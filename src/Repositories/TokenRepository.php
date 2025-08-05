<?php

namespace Lyn\LaravelCasServer\Repositories;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lyn\LaravelCasServer\Models\Token;

/**
 * 令牌仓储类
 * 
 * 负责管理客户端令牌的存储、检索和清理：
 * 1. 存储客户端令牌信息
 * 2. 根据会话ID检索令牌
 * 3. 根据用户ID检索令牌
 * 4. 清理过期令牌
 * 5. 支持单点登出时的令牌管理
 */
class TokenRepository
{
    /**
     * 令牌模型
     * 
     * @var string
     */
    protected $tokenModel;
    
    /**
     * 缓存前缀
     * 
     * @var string
     */
    protected $cachePrefix = 'cas_token:';
    
    /**
     * 缓存过期时间（秒）
     * 
     * @var int
     */
    protected $cacheExpiration = 3600;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->tokenModel = config('casserver.token_model', Token::class);
        $this->cacheExpiration = config('casserver.token_cache_expiration', 3600);
    }

    /**
     * 存储令牌信息
     *
     * @param array $tokenData 令牌数据
     * @return bool 存储是否成功
     */
    public function tokenStore(array $tokenData)
    {
        try {
            // 验证必需字段
            $requiredFields = ['session_id', 'client_id', 'token', 'user_id'];
            foreach ($requiredFields as $field) {
                if (!isset($tokenData[$field])) {
                    Log::warning('令牌存储失败：缺少必需字段', [
                        'missing_field' => $field,
                        'token_data' => $tokenData
                    ]);
                    return false;
                }
            }

            // 创建或更新令牌记录
            $tokenModel = $this->tokenModel;
            $token = $tokenModel::updateOrCreate(
                [
                    'session_id' => $tokenData['session_id'],
                    'client_id' => $tokenData['client_id']
                ],
                [
                    'token' => $tokenData['token'],
                    'user_id' => $tokenData['user_id'],
                    'service_url' => $tokenData['service_url'] ?? null,
                    'expires_at' => $tokenData['expires_at'] ?? now()->addHours(1),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            // 缓存令牌信息
            $cacheKey = $this->cachePrefix . $tokenData['session_id'] . ':' . $tokenData['client_id'];
            Cache::put($cacheKey, $token->toArray(), $this->cacheExpiration);

            Log::info('令牌存储成功', [
                'session_id' => $tokenData['session_id'],
                'client_id' => $tokenData['client_id'],
                'user_id' => $tokenData['user_id']
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('令牌存储失败', [
                'error' => $e->getMessage(),
                'token_data' => $tokenData
            ]);
            return false;
        }
    }

    /**
     * 根据会话ID获取令牌
     *
     * @param string $sessionId 会话ID
     * @return array 令牌列表
     */
    public function getTokensBySessionId($sessionId)
    {
        try {
            // 先从缓存获取
            $cachePattern = $this->cachePrefix . $sessionId . ':*';
            $cachedTokens = [];
            
            // Laravel Cache不直接支持模式匹配，所以直接查询数据库
            $tokenModel = $this->tokenModel;
            $tokens = $tokenModel::where('session_id', $sessionId)
                ->where('expires_at', '>', now())
                ->get();

            return $tokens->toArray();
        } catch (\Exception $e) {
            Log::error('根据会话ID获取令牌失败', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
            return [];
        }
    }

    /**
     * 根据用户ID获取令牌
     *
     * @param mixed $userId 用户ID
     * @return array 令牌列表
     */
    public function getTokensByUserId($userId)
    {
        try {
            $tokenModel = $this->tokenModel;
            $tokens = $tokenModel::where('user_id', $userId)
                ->where('expires_at', '>', now())
                ->get();

            return $tokens->toArray();
        } catch (\Exception $e) {
            Log::error('根据用户ID获取令牌失败', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            return [];
        }
    }

    /**
     * 根据令牌值获取令牌信息
     *
     * @param string $token 令牌值
     * @return array|null 令牌信息
     */
    public function getTokenByValue($token)
    {
        try {
            $tokenModel = $this->tokenModel;
            $tokenRecord = $tokenModel::where('token', $token)
                ->where('expires_at', '>', now())
                ->first();

            return $tokenRecord ? $tokenRecord->toArray() : null;
        } catch (\Exception $e) {
            Log::error('根据令牌值获取令牌失败', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 10) . '...'
            ]);
            return null;
        }
    }

    /**
     * 删除令牌
     *
     * @param string $sessionId 会话ID
     * @param string|null $clientId 客户端ID（可选）
     * @return bool 删除是否成功
     */
    public function deleteTokens($sessionId, $clientId = null)
    {
        try {
            $tokenModel = $this->tokenModel;
            $query = $tokenModel::where('session_id', $sessionId);
            
            if ($clientId) {
                $query->where('client_id', $clientId);
            }
            
            $deletedCount = $query->delete();

            // 清除相关缓存
            if ($clientId) {
                $cacheKey = $this->cachePrefix . $sessionId . ':' . $clientId;
                Cache::forget($cacheKey);
            } else {
                // 清除该会话的所有缓存（需要遍历）
                $this->clearSessionCache($sessionId);
            }

            Log::info('令牌删除成功', [
                'session_id' => $sessionId,
                'client_id' => $clientId,
                'deleted_count' => $deletedCount
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('令牌删除失败', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'client_id' => $clientId
            ]);
            return false;
        }
    }

    /**
     * 清理过期令牌
     *
     * @return int 清理的令牌数量
     */
    public function cleanExpiredTokens()
    {
        try {
            $tokenModel = $this->tokenModel;
            $deletedCount = $tokenModel::where('expires_at', '<=', now())->delete();

            Log::info('过期令牌清理完成', [
                'deleted_count' => $deletedCount
            ]);

            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('过期令牌清理失败', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * 清除会话相关的缓存
     *
     * @param string $sessionId 会话ID
     */
    protected function clearSessionCache($sessionId)
    {
        try {
            // 由于Laravel Cache不支持模式删除，这里采用简单的方法
            // 在实际生产环境中，可能需要使用Redis的SCAN命令或其他方法
            $tokenModel = $this->tokenModel;
            $tokens = $tokenModel::where('session_id', $sessionId)->get();
            
            foreach ($tokens as $token) {
                $cacheKey = $this->cachePrefix . $sessionId . ':' . $token->client_id;
                Cache::forget($cacheKey);
            }
        } catch (\Exception $e) {
            Log::warning('清除会话缓存失败', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
        }
    }
}