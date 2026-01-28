<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Listeners\SyncObrasOnLogin;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Login::class => [
            SyncObrasOnLogin::class,
        ],
    ];
}
