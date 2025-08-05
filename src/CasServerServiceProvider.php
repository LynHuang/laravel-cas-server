<?php

namespace Lyn\LaravelCasServer;

use Illuminate\Support\ServiceProvider;
use Lyn\LaravelCasServer\Commands\CasCreateClient;
use Lyn\LaravelCasServer\Contracts\Interactions\UserLogin;
use Lyn\LaravelCasServer\Contracts\Interactions\UserRegister;
use Lyn\LaravelCasServer\Contracts\Interactions\UserPassword;
use Lyn\LaravelCasServer\Http\Middleware\CasAuthenticate;
use Lyn\LaravelCasServer\Http\Middleware\CasTicketCheck;
use Lyn\LaravelCasServer\Interactions\DefaultUserLogin;
use Lyn\LaravelCasServer\Interactions\DefaultUserRegister;
use Lyn\LaravelCasServer\Interactions\DefaultUserPassword;
use Lyn\LaravelCasServer\Providers\EventServiceProvider;
use Route;

class CasServerServiceProvider extends ServiceProvider
{


    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(EventServiceProvider::class);
        
        // 绑定UserLogin接口的默认实现
        $this->app->bind(UserLogin::class, function ($app) {
            $customClass = config('casserver.interactions.user_login');
            if ($customClass && class_exists($customClass)) {
                return $app->make($customClass);
            }
            return $app->make(DefaultUserLogin::class);
        });
        
        // 绑定UserRegister接口的默认实现
        $this->app->bind(UserRegister::class, function ($app) {
            $customClass = config('casserver.interactions.user_register');
            if ($customClass && class_exists($customClass)) {
                return $app->make($customClass);
            }
            return $app->make(DefaultUserRegister::class);
        });
        
        // 绑定UserPassword接口的默认实现
        $this->app->bind(UserPassword::class, function ($app) {
            $customClass = config('casserver.interactions.user_password');
            if ($customClass && class_exists($customClass)) {
                return $app->make($customClass);
            }
            return $app->make(DefaultUserPassword::class);
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRoutes();
        //register migration
        $this->registerMigrations();
        //register the middleware
        $this->registerMiddleware();
        //register the commands
        $this->registerCommands();
        //register views
        $this->registerViews();

        //publish resource to laravel
        $this->publishes([
            __DIR__ . '/config/casserver.php' => config_path('casserver.php'), // 发布配置文件到 laravel 的config 下
        ]);
    }


    /**
     * load route file to laravel
     *
     */
    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/Routes/cas.php');
        });
    }


    /**
     * load route config
     *
     *
     * @return array
     */
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

    private function registerViews()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'casserver');
        
        // 发布视图文件
        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/casserver'),
        ], 'casserver-views');
    }
}
