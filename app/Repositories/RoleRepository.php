<?php

namespace App\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

class RoleRepository
{
    public function getAllWithCounts(): Collection
    {
        return Role::withCount(['permissions', 'users'])->get();
    }

    public function create(array $data): Role
    {
        return Role::create($data);
    }

    public function update(Role $role, array $data): void
    {
        $role->update($data);
    }

    public function delete(Role $role): void
    {
        $role->permissions()->detach();
        $role->delete();
    }

    public function hasUsers(Role $role): bool
    {
        return $role->users()->exists();
    }

    public function syncPermissions(Role $role, array $permissionIds): void
    {
        $role->permissions()->sync($permissionIds);
    }

    public function getPermissionIds(Role $role): array
    {
        return $role->permissions()->pluck('permissions.id')->toArray();
    }

    public function getByName(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }
}
