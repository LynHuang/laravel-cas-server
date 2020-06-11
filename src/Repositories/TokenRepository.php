<?php

namespace Lyn\LaravelCasServer\Repositories;

use Lyn\LaravelCasServer\Models\Client;
use Lyn\LaravelCasServer\Models\Token;

class TokenRepository
{
    /**
     * @var Client
     */
    protected $token;

    public function __construct(Token $token)
    {
        $this->token = $token;
    }

    public function tokenStore($data)
    {
        return $token = $this->token->insert($data);
    }

    public function getTokensWithClient($session_id)
    {
        return $this->token->where('server_session_id', $session_id)->with('client')->get();
    }
}
