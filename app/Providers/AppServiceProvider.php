<?php

namespace App\Providers;

use App\Services\PermissionRouteResolver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRouteResolver::class);
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised());
    }
}
