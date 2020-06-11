<?php

namespace Lyn\LaravelCasServer\Commands;

use http\Client;
use Illuminate\Console\Command;

class CasCreateClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cas:client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建一个cas客户端';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $name = $this->ask('the unique name of client: ');
        $redirect = $this->ask('the redirect of client: ');
        $logout_callback = $this->ask('the logout callback of client (if use the sso logout): ');

        if (!$name or !$redirect) {
            $this->error('name & redirect can not be null');
            return;
        }

        $client = new \Lyn\LaravelCasServer\Models\Client();
        $client->client_name = $name;
        $client->client_redirect = $redirect;
        $client->client_logout_callback = $logout_callback ?? '';
        $client->save();
        $this->info("client: $name create success");
    }
}
