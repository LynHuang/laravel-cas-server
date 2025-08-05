<?php

namespace Lyn\LaravelCasServer\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use Lyn\LaravelCasServer\Events\CasLogoutEvent;
use Lyn\LaravelCasServer\Events\CasUserLoggedOutEvent;
use Lyn\LaravelCasServer\Listeners\CasLogoutListener;
use Lyn\LaravelCasServer\Listeners\CasUserLoggedOutListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        CasLogoutEvent::class => [
            CasLogoutListener::class,
        ],
        CasUserLoggedOutEvent::class => [
            CasUserLoggedOutListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
