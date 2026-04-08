<?php

namespace App\Repositories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class PermissionRepository
{
    public function getDescriptionsByName(): SupportCollection
    {
        return Permission::pluck('description', 'name');
    }

    public function getAllGroupedByModule(): SupportCollection
    {
        return Permission::all()->groupBy('module');
    }

    public function getByName(string $name): ?Permission
    {
        return Permission::where('name', $name)->first();
    }

    public function getPermissionsByRoleId(int $roleId): Collection
    {
        return Permission::whereHas('roles', function ($query) use ($roleId) {
            $query->where('roles.id', $roleId);
        })->get();
    }
}
