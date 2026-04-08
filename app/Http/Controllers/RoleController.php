<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;
use App\Services\RoleService;

class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    public function index()
    {
        $data = $this->roleService->getIndexData();

        return view('admin.roles.index', $data);
    }

    public function create()
    {
        $data = $this->roleService->getCreateData();

        return view('admin.roles.create', $data);
    }

    public function store(RoleRequest $request)
    {
        $this->roleService->createRole(
            $request->safe()->only(['name', 'description', 'default_route']),
            $request->validated('permissions'),
        );

        return redirect()->route('roles.index')->with('success', '角色已建立');
    }

    public function edit(Role $role)
    {
        $data = $this->roleService->getEditData($role);

        return view('admin.roles.edit', $data);
    }

    public function update(RoleRequest $request, Role $role)
    {
        $this->roleService->updateRole(
            $role,
            $request->safe()->only(['name', 'description', 'default_route']),
            $request->validated('permissions'),
        );

        return redirect()->route('roles.index')->with('success', '角色已更新');
    }

    public function destroy(Role $role)
    {
        if (! $this->roleService->deleteRole($role)) {
            return redirect()->route('roles.index')->with('error', '此角色仍有使用者，無法刪除');
        }

        return redirect()->route('roles.index')->with('success', '角色已刪除');
    }
}
