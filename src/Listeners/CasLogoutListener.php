<?php

namespace Lyn\LaravelCasServer\Listeners;

use GuzzleHttp\Client;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Lyn\LaravelCasServer\Events\CasLogoutEvent;
use Lyn\LaravelCasServer\Repositories\TokenRepository;

class CasLogoutListener
{
    protected $tokenRepository;

    /**
     * Create the event listener.
     * @param TokenRepository $tokenRepository
     * @return void
     */
    public function __construct(TokenRepository $tokenRepository)
    {
        $this->tokenRepository = $tokenRepository;
    }

    /**
     * Handle the event.
     *
     * @param CasLogoutEvent $event
     * @return void
     */
    public function handle(CasLogoutEvent $event)
    {
        $session_id = $event->getSessionId();
        $tokens = $this->tokenRepository->getTokensWithClient($session_id);

        foreach ($tokens as $token) {
            $http = new Client();
            $http->post($token->client->client_logout_callback, [
                'token' => $token->client_token,
            ]);
        }
    }
}
