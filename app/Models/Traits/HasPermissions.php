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
}
