<?php

namespace Lyn\LaravelCasServer;

use Illuminate\Support\ServiceProvider;
use Lyn\LaravelCasServer\Commands\CasCreateClient;
use Lyn\LaravelCasServer\Http\Middleware\CasAuthenticate;
use Lyn\LaravelCasServer\Http\Middleware\CasTicketCheck;
use Lyn\LaravelCasServer\Providers\EventServiceProvider;
use Route;

class CasServerServiceProvider extends ServiceProvider
{
    protected $defer = true; // 延迟加载服务


    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
		$this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //加载路由
        $this->registerRoutes();
        //加载迁移
        $this->registerMigrations();
        //register the middleware
        $this->registerMiddleware();
        //register the commands
        $this->registerCommands();

        //发布资源
        $this->publishes([
            __DIR__ . '/config/casserver.php' => config_path('casserver.php'), // 发布配置文件到 laravel 的config 下
        ]);
    }

    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/Routes/cas.php');
        });
    }


    private function routeConfiguration()
    {
        return [
            'domain' => config('casserver.route.domain', null),
            'namespace' => 'Lyn\LaravelCasServer\Http\Controllers',
            'prefix' => config('casserver.route.prefix', 'cas'),
            'middleware' => config('casserver.route.middleware'),
        ];
    }

    private function registerMigrations()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/Migrations');
        }
    }

    private function registerMiddleware()
    {
        $this->app['router']->aliasMiddleware('cas_auth', CasAuthenticate::class);
        $this->app['router']->aliasMiddleware('cas_ticket_check', CasTicketCheck::class);
    }

    private function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CasCreateClient::class
            ]);
        }
    }
}
