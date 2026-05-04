<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Authenticated;

/**
 * 處理 Authenticated 事件 — 涵蓋 remember-me 自動登入。
 *
 * 一般登入由 AuthService::loginSession() 主動呼叫 loadPermissionsToSession()。
 * 但 remember-me 不會走 attempt() → loginSession()，session 中沒有 auth.permissions。
 * 此 listener 在每次 Authenticated 觸發時，若 session 內權限缺失或 stale 則重新載入。
 */
class EnsurePermissionsLoadedToSession
{
    public function handle(Authenticated $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        if ($user->permissionsSessionIsStale()) {
            $user->loadPermissionsToSession();
        }
    }
}
