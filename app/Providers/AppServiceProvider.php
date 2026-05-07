<?php

namespace App\Providers;

use App\Services\PermissionRouteResolver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRouteResolver::class);
    }
}
