<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * @var array<string, array{label: string, actions: array<string, string>}>
     */
    private array $modules = [
        'Dashboard' => [
            'label' => '儀表板',
            'actions' => [
                'index' => '首頁',
                'detail' => '詳細頁',
            ],
        ],
        'Post' => [
            'label' => '文章',
            'actions' => [
                'index' => '列表',
                'create' => '新增',
                'update' => '編輯',
                'delete' => '刪除',
            ],
        ],
        'User' => [
            'label' => '使用者',
            'actions' => [
                'index' => '列表',
                'create' => '新增',
                'update' => '編輯',
                'delete' => '刪除',
            ],
        ],
        'Role' => [
            'label' => '角色',
            'actions' => [
                'index' => '列表',
                'create' => '新增',
                'update' => '編輯',
                'delete' => '刪除',
            ],
        ],
        'Grade' => [
            'label' => '版本',
            'actions' => [
                'index' => '列表',
                'create' => '新增',
                'update' => '編輯',
            ],
        ],
        'Shop' => [
            'label' => '商店',
            'actions' => [
                'index' => '列表',
                'update' => '編輯',
            ],
        ],
        'Addon' => [
            'label' => '附加功能',
            'actions' => [
                'index' => '列表',
                'create' => '新增',
                'update' => '編輯',
                'delete' => '刪除',
            ],
        ],
        'Bill' => [
            'label' => '帳務',
            'actions' => [
                'index'    => '列表',
                'create'   => '建立',
                'pay'      => '付款',
                'writeoff' => '銷帳',
            ],
        ],
        'Conference' => [
            'label' => '說明會',
            'actions' => [
                'index'  => '列表',
                'create' => '新增',
                'update' => '編輯',
            ],
        ],
    ];

    public function run(): void
    {
        $this->syncPermissions();
        $this->syncRoles();
    }

    private function syncPermissions(): void
    {
        $validNames = [];

        foreach ($this->modules as $module => $config) {
            foreach ($config['actions'] as $action => $actionLabel) {
                $name = "{$module}.{$action}";
                $description = "{$config['label']} - {$actionLabel}";
                $validNames[] = $name;

                Permission::updateOrCreate(
                    ['name' => $name],
                    ['module' => $module, 'action' => $action, 'description' => $description],
                );
            }
        }

        $removedPermissions = Permission::whereNotIn('name', $validNames)->get();
        foreach ($removedPermissions as $permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }

    private function syncRoles(): void
    {
        $allPermissions = Permission::all();
        $indexPermissions = Permission::where('action', 'index')->get();

        $roles = [
            'Admin' => [
                'description' => '系統管理員，擁有全部權限',
                'default_route' => 'Dashboard.index',
                'permissions' => $allPermissions,
            ],
            'Viewer' => [
                'description' => '檢視者，僅能檢視列表',
                'default_route' => 'Dashboard.index',
                'permissions' => $indexPermissions,
            ],
        ];

        foreach ($roles as $name => $config) {
            $defaultPermission = $allPermissions->firstWhere('name', $config['default_route']);
            if (! $defaultPermission) {
                throw new \RuntimeException("default_route '{$config['default_route']}' 對應的 permission 不存在");
            }

            $role = Role::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $config['description'],
                    'default_route' => $config['default_route'],
                ],
            );

            $permissionIds = $config['permissions']->pluck('id');
            if (! $permissionIds->contains($defaultPermission->id)) {
                $permissionIds->push($defaultPermission->id);
            }

            $role->permissions()->sync($permissionIds);
        }
    }
}
