<?php

namespace App\Services;

use App\Models\Role;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class RoleService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private PermissionRepository $permissionRepository,
    ) {}

    /**
     * @return array{roles: Collection, permissionDescriptions: SupportCollection}
     */
    public function getIndexData(): array
    {
        return [
            'roles' => $this->roleRepository->getAllWithCounts(),
            'permissionDescriptions' => $this->permissionRepository->getDescriptionsByName(),
        ];
    }

    /**
     * @return array{permissions: SupportCollection}
     */
    public function getCreateData(): array
    {
        return [
            'permissions' => $this->permissionRepository->getAllGroupedByModule(),
        ];
    }

    /**
     * @return array{role: Role, permissions: SupportCollection, rolePermissionIds: array<int>}
     */
    public function getEditData(Role $role): array
    {
        return [
            'role' => $role,
            'permissions' => $this->permissionRepository->getAllGroupedByModule(),
            'rolePermissionIds' => $this->roleRepository->getPermissionIds($role),
        ];
    }

    public function findRoleById(int $id): ?Role
    {
        return $this->roleRepository->findById($id);
    }

    public function createRole(array $data, array $permissionIds): Role
    {
        $role = $this->roleRepository->create($data);

        $permissionIds = $this->ensureDefaultPermission($role->default_route, $permissionIds);
        $this->roleRepository->syncPermissions($role, $permissionIds);

        return $role;
    }

    public function updateRole(Role $role, array $data, array $permissionIds): void
    {
        $this->roleRepository->update($role, $data);

        $permissionIds = $this->ensureDefaultPermission($role->default_route, $permissionIds);
        $this->roleRepository->syncPermissions($role, $permissionIds);
    }

    /**
     * @return bool 是否成功刪除（false 表示仍有使用者）
     */
    public function deleteRole(Role $role): bool
    {
        if ($this->roleRepository->hasUsers($role)) {
            return false;
        }

        $this->roleRepository->delete($role);

        return true;
    }

    /**
     * 確保 default_route 對應的權限一定包含在權限清單中
     *
     * @return array<int>
     */
    private function ensureDefaultPermission(string $defaultRoute, array $permissionIds): array
    {
        $defaultPermission = $this->permissionRepository->getByName($defaultRoute);

        if ($defaultPermission && ! in_array($defaultPermission->id, $permissionIds)) {
            $permissionIds[] = $defaultPermission->id;
        }

        return $permissionIds;
    }
}
