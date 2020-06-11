<?php

namespace Lyn\LaravelCasServer\Repositories;

use Lyn\LaravelCasServer\Models\Client;

class ClientRepository
{
    /**
     * @var Client
     */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClientByName($name)
    {
        return $this->client->where('client_name', $name)->first();
    }

    public function getClientById($id)
    {
        return $this->client->find($id);
    }
}
