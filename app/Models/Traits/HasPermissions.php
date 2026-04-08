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
        $permissions = session('permissions', []);

        return in_array($name, $permissions);
    }

    public function hasRole(string $name): bool
    {
        return $this->role?->name === $name;
    }

    public function assignRole(Role $role): void
    {
        $this->role_id = $role->id;
        $this->save();
        $this->clearPermissionCache();
    }

    public function getDefaultRoute(): ?string
    {
        return $this->role?->default_route;
    }

    public function loadPermissionsToSession(): void
    {
        $permissions = $this->role
            ? $this->role->permissions()->pluck('name')->toArray()
            : [];

        session(['permissions' => $permissions]);
    }

    public function clearPermissionCache(): void
    {
        session()->forget('permissions');
    }
}
