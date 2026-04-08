<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount(['permissions', 'users'])->get();
        $permissionDescriptions = Permission::pluck('description', 'name');

        return view('admin.roles.index', compact('roles', 'permissionDescriptions'));
    }

    public function create()
    {
        $permissions = Permission::all()->groupBy('module');

        return view('admin.roles.create', compact('permissions'));
    }

    public function store(RoleRequest $request)
    {
        $role = Role::create($request->safe()->only(['name', 'description', 'default_route']));

        $permissionIds = $request->validated('permissions');
        $defaultPermission = Permission::where('name', $role->default_route)->first();
        if ($defaultPermission && ! in_array($defaultPermission->id, $permissionIds)) {
            $permissionIds[] = $defaultPermission->id;
        }

        $role->permissions()->sync($permissionIds);

        return redirect()->route('roles.index')->with('success', '角色已建立');
    }

    public function edit(Role $role)
    {
        $permissions = Permission::all()->groupBy('module');
        $rolePermissionIds = $role->permissions()->pluck('permissions.id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissionIds'));
    }

    public function update(RoleRequest $request, Role $role)
    {
        $role->update($request->safe()->only(['name', 'description', 'default_route']));

        $permissionIds = $request->validated('permissions');
        $defaultPermission = Permission::where('name', $role->default_route)->first();
        if ($defaultPermission && ! in_array($defaultPermission->id, $permissionIds)) {
            $permissionIds[] = $defaultPermission->id;
        }

        $role->permissions()->sync($permissionIds);

        return redirect()->route('roles.index')->with('success', '角色已更新');
    }

    public function delete(Role $role)
    {
        if ($role->users()->exists()) {
            return redirect()->route('roles.index')->with('error', '此角色仍有使用者，無法刪除');
        }

        $role->permissions()->detach();
        $role->delete();

        return redirect()->route('roles.index')->with('success', '角色已刪除');
    }
}
