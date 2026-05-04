<?php

namespace App\Providers;

use App\Models\Traits\HasPermissions;
use App\Services\PermissionRouteResolver;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PermissionRouteResolver::class);
    }

    public function boot(): void
    {
        // 涵蓋 remember-me 自動登入：Login event 不會觸發、僅 Authenticated 會觸發。
        // 一般登入由 AuthService::loginSession() 主動載入；這裡確保 remember-me 也會 reload。
        Event::listen(Authenticated::class, function (Authenticated $event): void {
            $user = $event->user;

            if (! in_array(HasPermissions::class, class_uses_recursive($user), true)) {
                return;
            }

            if ($user->permissionsSessionIsStale()) {
                $user->loadPermissionsToSession();
            }
        });
    }
}
