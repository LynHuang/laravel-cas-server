<?php

namespace Lyn\LaravelCasServer\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Lyn\LaravelCasServer\Models\Client;

/**
 * CAS票据管理服务
 * 
 * 负责管理CAS协议中的各种票据：
 * 1. TGT (Ticket Granting Ticket) - 票据授权票据，用户主认证票据
 * 2. ST (Service Ticket) - 服务票据，一次性使用的客户端验证票据
 * 3. PGT (Proxy Granting Ticket) - 代理授权票据（可选功能）
 * 4. PT (Proxy Ticket) - 代理票据（可选功能）
 * 
 * 票据生命周期管理：
 * - 生成：创建新票据并设置过期时间
 * - 验证：检查票据有效性和权限
 * - 清理：自动清理过期票据
 */
class TicketManger
{
    /**
     * TGT票据前缀
     */
    const TGT_PREFIX = 'TGT-';
    
    /**
     * ST票据前缀
     */
    const ST_PREFIX = 'ST-';
    
    /**
     * PGT票据前缀
     */
    const PGT_PREFIX = 'PGT-';
    
    /**
     * PT票据前缀
     */
    const PT_PREFIX = 'PT-';

    /**
     * 生成TGT票据
     * TGT是CAS协议的核心票据，代表用户已通过认证
     * 
     * @param string|int $user_id 用户ID
     * @return string TGT票据字符串
     */
    public function generateTGT($user_id)
    {
        $tgt = self::TGT_PREFIX . Str::uuid();
        $expireAt = Carbon::now()->addSeconds(config('casserver.ticket_expire', 7200)); // 默认2小时
        
        // 将TGT存储到数据库
        DB::table('cas_ticket_granting_tickets')->insert([
            'user_id' => $user_id,
            'tgt' => $tgt,
            'expire_at' => $expireAt,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        
        // 同时缓存到Redis/Cache中以提高查询性能
        $this->cacheTGT($tgt, $user_id, $expireAt);
        
        return $tgt;
    }
    
    /**
     * 生成ST票据
     * ST是一次性使用的票据，用于客户端应用验证用户身份
     * 
     * @param string $tgt 关联的TGT票据
     * @param int $client_id 客户端ID
     * @return string ST票据字符串
     */
    public function generateST($tgt, $client_id)
    {
        $st = self::ST_PREFIX . Str::uuid();
        $expireAt = Carbon::now()->addSeconds(config('casserver.st_expire', 300)); // 默认5分钟
        
        // 将ST存储到数据库
        DB::table('cas_service_tickets')->insert([
            'st' => $st,
            'tgt' => $tgt,
            'client_id' => $client_id,
            'expire_at' => $expireAt,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
        
        // 缓存ST票据
        $this->cacheST($st, $tgt, $client_id, $expireAt);
        
        return $st;
    }
    
    /**
     * 申请票据（兼容旧接口）
     * @param $user_id
     * @return string
     * @deprecated 使用 generateST 替代
     */
    public function applyTicket($user_id)
    {
        // 为了向后兼容，这里生成一个简单的ST票据
        $ticket = self::ST_PREFIX . Str::uuid();
        $this->cacheTicket($ticket, $user_id);
        return $ticket;
    }

    /**
     * 缓存TGT票据到Cache中
     * 
     * @param string $tgt TGT票据
     * @param string|int $user_id 用户ID
     * @param Carbon $expireAt 过期时间
     */
    protected function cacheTGT($tgt, $user_id, $expireAt)
    {
        $ttl = $expireAt->diffInSeconds(Carbon::now());
        Cache::put("tgt:{$tgt}", [
            'user_id' => $user_id,
            'expire_at' => $expireAt->toDateTimeString()
        ], $ttl);
    }
    
    /**
     * 缓存ST票据到Cache中
     * 
     * @param string $st ST票据
     * @param string $tgt 关联的TGT
     * @param int $client_id 客户端ID
     * @param Carbon $expireAt 过期时间
     */
    protected function cacheST($st, $tgt, $client_id, $expireAt)
    {
        $ttl = $expireAt->diffInSeconds(Carbon::now());
        Cache::put("st:{$st}", [
            'tgt' => $tgt,
            'client_id' => $client_id,
            'expire_at' => $expireAt->toDateTimeString()
        ], $ttl);
    }

    /**
     * 缓存票据（兼容旧接口）
     * @param $ticket
     * @param $user_id
     * @deprecated 使用具体的缓存方法替代
     */
    public function cacheTicket($ticket, $user_id)
    {
        Cache::put($ticket, $user_id, config('casserver.ticket_expire', 300));
    }

    /**
     * 验证TGT票据
     * 
     * @param string $tgt TGT票据
     * @return bool 票据是否有效
     */
    public function validateTGT($tgt)
    {
        // 首先检查缓存
        if (Cache::has("tgt:{$tgt}")) {
            return true;
        }
        
        // 检查数据库中的票据
        $ticket = DB::table('cas_ticket_granting_tickets')
            ->where('tgt', $tgt)
            ->where('expire_at', '>', Carbon::now())
            ->first();
            
        if ($ticket) {
            // 重新缓存票据
            $expireAt = Carbon::parse($ticket->expire_at);
            $this->cacheTGT($tgt, $ticket->user_id, $expireAt);
            return true;
        }
        
        return false;
    }
    
    /**
     * 验证ST票据
     * 
     * @param string $st ST票据
     * @param int $client_id 客户端ID（可选，用于额外验证）
     * @return bool 票据是否有效
     */
    public function validateST($st, $client_id = null)
    {
        // 首先检查缓存
        $cached = Cache::get("st:{$st}");
        if ($cached) {
            if ($client_id && $cached['client_id'] != $client_id) {
                return false;
            }
            return true;
        }
        
        // 检查数据库中的票据
        $query = DB::table('cas_service_tickets')
            ->where('st', $st)
            ->where('expire_at', '>', Carbon::now());
            
        if ($client_id) {
            $query->where('client_id', $client_id);
        }
        
        $ticket = $query->first();
        
        if ($ticket) {
            // 重新缓存票据
            $expireAt = Carbon::parse($ticket->expire_at);
            $this->cacheST($st, $ticket->tgt, $ticket->client_id, $expireAt);
            return true;
        }
        
        return false;
    }

    /**
     * 验证票据（兼容旧接口）
     * @param $ticket
     * @return bool
     */
    public function validateTicket($ticket)
    {
        // 根据票据前缀判断票据类型
        if (Str::startsWith($ticket, self::TGT_PREFIX)) {
            return $this->validateTGT($ticket);
        } elseif (Str::startsWith($ticket, self::ST_PREFIX)) {
            return $this->validateST($ticket);
        }
        
        // 兼容旧的缓存方式
        return Cache::has($ticket);
    }

    /**
     * 获取TGT票据数据
     * 
     * @param string $tgt TGT票据
     * @return array|null 票据数据
     */
    public function getTGTData($tgt)
    {
        // 首先检查缓存
        $cached = Cache::get("tgt:{$tgt}");
        if ($cached) {
            return $cached;
        }
        
        // 从数据库获取
        $ticket = DB::table('cas_ticket_granting_tickets')
            ->where('tgt', $tgt)
            ->where('expire_at', '>', Carbon::now())
            ->first();
            
        if ($ticket) {
            $data = [
                'user_id' => $ticket->user_id,
                'expire_at' => $ticket->expire_at
            ];
            
            // 重新缓存
            $expireAt = Carbon::parse($ticket->expire_at);
            $this->cacheTGT($tgt, $ticket->user_id, $expireAt);
            
            return $data;
        }
        
        return null;
    }
    
    /**
     * 获取ST票据数据
     * 
     * @param string $st ST票据
     * @return array|null 票据数据
     */
    public function getSTData($st)
    {
        // 首先检查缓存
        $cached = Cache::get("st:{$st}");
        if ($cached) {
            return $cached;
        }
        
        // 从数据库获取
        $ticket = DB::table('cas_service_tickets')
            ->where('st', $st)
            ->where('expire_at', '>', Carbon::now())
            ->first();
            
        if ($ticket) {
            $data = [
                'tgt' => $ticket->tgt,
                'client_id' => $ticket->client_id,
                'expire_at' => $ticket->expire_at
            ];
            
            // 重新缓存
            $expireAt = Carbon::parse($ticket->expire_at);
            $this->cacheST($st, $ticket->tgt, $ticket->client_id, $expireAt);
            
            return $data;
        }
        
        return null;
    }

    /**
     * 获取票据数据（兼容旧接口）
     * @param $ticket
     * @return mixed
     */
    public function getTicketData($ticket)
    {
        // 根据票据前缀判断票据类型
        if (Str::startsWith($ticket, self::TGT_PREFIX)) {
            return $this->getTGTData($ticket);
        } elseif (Str::startsWith($ticket, self::ST_PREFIX)) {
            return $this->getSTData($ticket);
        }
        
        // 兼容旧的缓存方式
        return Cache::get($ticket);
    }

    /**
     * 移除TGT票据
     * 同时移除所有关联的ST票据
     * 
     * @param string $tgt TGT票据
     */
    public function removeTGT($tgt)
    {
        // 从数据库删除TGT
        DB::table('cas_ticket_granting_tickets')
            ->where('tgt', $tgt)
            ->delete();
            
        // 删除所有关联的ST票据
        $serviceTickets = DB::table('cas_service_tickets')
            ->where('tgt', $tgt)
            ->get();
            
        foreach ($serviceTickets as $st) {
            Cache::forget("st:{$st->st}");
        }
        
        DB::table('cas_service_tickets')
            ->where('tgt', $tgt)
            ->delete();
            
        // 从缓存删除TGT
        Cache::forget("tgt:{$tgt}");
    }
    
    /**
     * 移除ST票据
     * ST票据使用后应立即删除（一次性使用）
     * 
     * @param string $st ST票据
     */
    public function removeST($st)
    {
        // 从数据库删除ST
        DB::table('cas_service_tickets')
            ->where('st', $st)
            ->delete();
            
        // 从缓存删除ST
        Cache::forget("st:{$st}");
    }

    /**
     * 移除票据（兼容旧接口）
     * @param $ticket
     */
    public function removeTicket($ticket = null)
    {
        // 如果没有传入票据参数，使用实例属性
        $ticketToRemove = $ticket ?: $this->ticket;
        
        // 根据票据前缀判断票据类型
        if (Str::startsWith($ticketToRemove, self::TGT_PREFIX)) {
            $this->removeTGT($ticketToRemove);
        } elseif (Str::startsWith($ticketToRemove, self::ST_PREFIX)) {
            $this->removeST($ticketToRemove);
        } else {
            // 兼容旧的缓存方式
            Cache::forget($ticketToRemove);
        }
    }
    
    /**
     * 清理过期票据
     * 定期清理数据库中的过期票据，释放存储空间
     */
    public function cleanExpiredTickets()
    {
        $now = Carbon::now();
        
        // 清理过期的TGT票据
        DB::table('cas_ticket_granting_tickets')
            ->where('expire_at', '<', $now)
            ->delete();
            
        // 清理过期的ST票据
        DB::table('cas_service_tickets')
            ->where('expire_at', '<', $now)
            ->delete();
            
        // 清理过期的PGT票据
        DB::table('cas_proxy_granting_tickets')
            ->where('expire_at', '<', $now)
            ->delete();
            
        // 清理过期的PT票据
        DB::table('cas_proxy_tickets')
            ->where('expire_at', '<', $now)
            ->delete();
    }
    
    /**
     * 获取用户的所有有效TGT票据
     * 
     * @param string|int $user_id 用户ID
     * @return array TGT票据列表
     */
    public function getUserTGTs($user_id)
    {
        return DB::table('cas_ticket_granting_tickets')
            ->where('user_id', $user_id)
            ->where('expire_at', '>', Carbon::now())
            ->pluck('tgt')
            ->toArray();
    }
    
    /**
     * 记录票据验证
     * 用于审计和日志记录
     * 
     * @param string $ticket 票据
     * @param int $client_id 客户端ID
     */
    public function recordTicketValidation($ticket, $client_id)
    {
        DB::table('cas_ticket_validation_records')->insert([
            'ticket' => $ticket,
            'client_id' => $client_id,
            'validated_at' => Carbon::now(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
    
    // 保留原有的实例属性和方法以保持向后兼容
    protected $ticket;
    protected $data;

    /**
     * TicketManger constructor.
     * @param string $ticket
     */
    public function __construct($ticket = null)
    {
        $this->ticket = $ticket;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return string
     */
    public static function applyTicketFromRequest($request)
    {
        $client_ip = $request->ip();
        $guid = Str::uuid();
        $time = time();
        return md5($client_ip . $guid . $time);
    }

    public function putTicketToCache($request, Client $client)
    {
        Cache::put($this->ticket, [
            'time' => time(),
            'user_id' => $request->user()->id,
            'session_id' => $request->session()->getId(),
            'client_id' => $client->id,
            'ip' => $request->ip()
        ], config('casserver.ticket_expire', 300));
    }

    public function isTicketValid()
    {
        return Cache::get($this->ticket, '') ? true : false;
    }

    public function getDataByTicket()
    {
        if (!$this->data && $this->isTicketValid())
            $this->data = Cache::get($this->ticket);
        return $this->data;
    }
}
