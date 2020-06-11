<?php

namespace Lyn\LaravelCasServer\Http\Middleware;

use Closure;
use Cache;
use Lyn\LaravelCasServer\Services\TicketManger;

class CasTicketCheck
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $ticket = $request->get('ticket', '');
        $ticketManager = new TicketManger($ticket);
        if (!$ticket || !$ticketManager->isTicketValid() ||
            $ticketManager->getDataByTicket()['ip'] != $request->ip()) {
            return response()->json([
                'code' => 401,
                'status' => 'error',
                'msg' => 'not have effect ticket'
            ]);
        }
        return $next($request);
    }
}
