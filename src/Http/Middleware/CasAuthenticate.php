<?php

namespace Lyn\LaravelCasServer\Http\Middleware;

use Closure;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Exceptions\CAS\CasException;
use Lyn\LaravelCasServer\Repositories\ClientRepository;
use Lyn\LaravelCasServer\Services\TicketManger;

class CasAuthenticate
{
    protected $loginInteraction;
    protected $clientRepository;

    public function __construct(
        UserLogin $loginInteraction,
        ClientRepository $clientRepository
    )
    {
        $this->loginInteraction = $loginInteraction;
        $this->clientRepository = $clientRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $from = $request->get('from', '');
        $client = $this->clientRepository->getClientByName($from);
        if (!$client) {
            return $this->loginInteraction->showErrorPage(new CasException(CasException::UNAUTHORIZED_SERVICE_PROXY));
        }

        if (!$request->user()) {
            //CAS not login or do not have ticket
            return $next($request);
        }

        $ticket = TicketManger::applyTicket($request);

        //put the ticket and other info to cache
        $ticketManager = new TicketManger($ticket);
        $ticketManager->putTicketToCache($request, $client);

        return redirect($client->client_redirect . '?ticket=' . $ticket);
    }
}
