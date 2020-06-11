<?php

namespace Lyn\LaravelCasServer\Services;

use Illuminate\Http\Request;
use Lyn\LaravelCasServer\Models\Client;
use Str;
use Cache;

class TicketManger
{
    protected $ticket;
    protected $data;

    /**
     * TicketManger constructor.
     * @param string $ticket
     */
    public function __construct($ticket)
    {
        $this->ticket = $ticket;
    }

    /**
     * @param Request $request
     * @return string
     */
    public static function applyTicket(Request $request)
    {
        $client_ip = $request->ip();
        $guid = Str::uuid();
        $time = time();
        return md5($client_ip . $guid . $time);
    }

    public function putTicketToCache(Request $request, Client $client)
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

    public function removeTicket()
    {
        Cache::forget($this->ticket);
    }
}
