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
        // 全站密碼政策；正式環境無對外網路，不使用 uncompromised()（HIBP）
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
    }
}
