<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    #[RequiresPermission('Role.index')]
    public function index()
    {
        $data = $this->roleService->getIndexData();

        return view('admin.roles.index', $data);
    }

    #[RequiresPermission('Role.create')]
    public function create()
    {
        $data = $this->roleService->getCreateData();

        return view('admin.roles.create', $data);
    }

    #[RequiresPermission('Role.create')]
    public function store(RoleRequest $request)
    {
        $this->roleService->createRole(
            $request->safe()->only(['name', 'description', 'default_route']),
            $request->validated('permissions'),
        );

        return redirect()->route('roles.index')->with('success', '角色已建立');
    }

    #[RequiresPermission('Role.update')]
    public function edit(int $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return redirect()->route('roles.index')->with('error', '找不到該角色');
        }

        $data = $this->roleService->getEditData($role);

        return view('admin.roles.edit', $data);
    }

    #[RequiresPermission('Role.update')]
    public function update(RoleRequest $request, int $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return redirect()->route('roles.index')->with('error', '找不到該角色');
        }

        $this->roleService->updateRole(
            $role,
            $request->safe()->only(['name', 'description', 'default_route']),
            $request->validated('permissions'),
        );

        return redirect()->route('roles.index')->with('success', '角色已更新');
    }

    #[RequiresPermission('Role.delete')]
    public function destroy(int $id)
    {
        $role = Role::find($id);
        if (!$role) {
            return redirect()->route('roles.index')->with('error', '找不到該角色');
        }

        if (! $this->roleService->deleteRole($role)) {
            return redirect()->route('roles.index')->with('error', '此角色仍有使用者，無法刪除');
        }

        return redirect()->route('roles.index')->with('success', '角色已刪除');
    }
}
