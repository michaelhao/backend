<?php

namespace App\Models\Traits;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasPermissions
{
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermissionTo(string $name): bool
    {
        $permissions = session('auth.permissions', []);

        return in_array($name, $permissions, true);
    }

    public function getDefaultRoute(): ?string
    {
        return $this->role?->default_route;
    }

    public function loadPermissionsToSession(): void
    {
        // 確保讀到最新的 role 資料 — 避免 Eloquent 快取住的舊關聯造成 role_id 已換但載入舊角色權限
        $this->unsetRelation('role');

        $permissions = $this->role
            ? $this->role->permissions()->pluck('name')->toArray()
            : [];

        session([
            'auth.permissions' => $permissions,
            'auth.permissions_version' => $this->currentPermissionsVersion(),
        ]);
    }

    /**
     * 以 user.updated_at 與 role.updated_at 中較新者作為權限版本戳。
     * 異動 role permissions 時會 touch role；異動 user.role_id 時會 touch user。
     * 直接走 DB 查詢以避免取到 Eloquent 快取住的舊值（測試 actingAs 場景特別重要）。
     */
    public function currentPermissionsVersion(): ?int
    {
        if (! $this->role_id) {
            return null;
        }

        $userTs = static::query()->where('id', $this->getKey())->value('updated_at');
        $roleTs = Role::query()->where('id', $this->role_id)->value('updated_at');

        return max(
            $userTs?->getTimestamp() ?? 0,
            $roleTs?->getTimestamp() ?? 0,
        );
    }

    public function permissionsSessionIsStale(): bool
    {
        if (! session()->has('auth.permissions')) {
            return true;
        }

        return session('auth.permissions_version') !== $this->currentPermissionsVersion();
    }
}
