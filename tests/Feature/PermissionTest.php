<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    private function seedPermissions(): void
    {
        $this->seed(PermissionSeeder::class);
    }

    private function createUserWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        $user = User::factory()->create(['role_id' => $role->id]);

        // 模擬登入時載入權限至 session
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

    public function test_user_without_role_is_redirected_to_no_role_page(): void
    {
        $this->seedPermissions();

        $user = User::factory()->create(['role_id' => null]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('no-role'));
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_admin_can_access_routes_that_viewer_cannot(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $this->get(route('roles.create'))->assertStatus(200);
    }

    public function test_viewer_can_access_index_pages(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');

        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_viewer_is_redirected_when_accessing_non_index_pages(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');

        $response = $this->get(route('roles.create'));

        $response->assertRedirect();
    }

    public function test_user_without_permission_is_redirected_to_default_route(): void
    {
        $this->seedPermissions();

        $role = Role::factory()->create(['default_route' => 'Dashboard.index']);
        $dashboardPermission = Permission::where('name', 'Dashboard.index')->first();
        $role->permissions()->sync([$dashboardPermission->id]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        $response = $this->get(route('roles.index'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_abort_403_when_default_route_equals_current_permission(): void
    {
        $this->seedPermissions();

        // 建立一個角色，default_route 指向 Role.index 但沒有該權限
        $role = Role::factory()->create(['default_route' => 'Role.index']);
        // 不給任何權限
        $role->permissions()->sync([]);

        $user = User::factory()->create(['role_id' => $role->id]);
        $this->actingAs($user);
        $user->loadPermissionsToSession();

        $response = $this->get(route('roles.index'));

        $response->assertStatus(403);
    }

    public function test_middleware_auto_infers_permission_from_controller(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        // DashboardController@index → Dashboard.index
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_closure_route_in_permission_group_returns_403(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        // 註冊一個 closure route 在 permission middleware group 內
        Route::middleware(['auth', 'permission'])->get('/closure-test', function () {
            return 'should not reach here';
        });

        $response = $this->get('/closure-test');

        $response->assertStatus(403);
    }

    public function test_role_crud_create(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $permission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->post(route('roles.store'), [
            'name' => 'Editor',
            'description' => '編輯者',
            'default_route' => 'Dashboard.index',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Editor']);
    }

    public function test_role_crud_update(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $role = Role::where('name', 'Viewer')->first();
        $permission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->put(route('roles.update', $role), [
            'name' => 'Updated Viewer',
            'description' => '更新後的檢視者',
            'default_route' => 'Dashboard.index',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Updated Viewer']);
    }

    public function test_role_crud_delete(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $role = Role::where('name', 'Viewer')->first();

        $response = $this->delete(route('roles.destroy', $role));

        $response->assertOk();
        $response->assertExactJson(['message' => '角色已刪除']);
        $this->assertDatabaseMissing('roles', ['name' => 'Viewer']);
    }

    public function test_cannot_delete_role_with_users(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $adminRole = Role::where('name', 'Admin')->first();

        $response = $this->delete(route('roles.destroy', $adminRole));

        $response->assertStatus(422);
        $response->assertExactJson(['message' => '此角色仍有使用者，無法刪除']);
        $this->assertDatabaseHas('roles', ['name' => 'Admin']);
    }

    public function test_default_route_permission_is_auto_included_on_store(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $otherPermission = Permission::where('name', 'Grade.index')->first();
        $defaultPermission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->post(route('roles.store'), [
            'name' => 'TestRole',
            'default_route' => 'Dashboard.index',
            'permissions' => [$otherPermission->id],
        ]);

        $response->assertRedirect(route('roles.index'));

        $role = Role::where('name', 'TestRole')->first();
        $this->assertTrue($role->permissions->contains('id', $defaultPermission->id));
    }

    public function test_default_route_permission_is_auto_included_on_update(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $role = Role::where('name', 'Viewer')->first();
        $otherPermission = Permission::where('name', 'Grade.index')->first();
        $defaultPermission = Permission::where('name', 'Dashboard.index')->first();

        $this->put(route('roles.update', $role), [
            'name' => 'Updated Viewer',
            'description' => 'Test update',
            'default_route' => 'Dashboard.index',
            'permissions' => [$otherPermission->id],
        ]);

        $role->refresh();
        $this->assertTrue($role->permissions->contains('id', $defaultPermission->id));
    }

    public function test_store_role_fails_with_duplicate_name(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $permission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->post(route('roles.store'), [
            'name' => 'Admin',
            'default_route' => 'Dashboard.index',
            'permissions' => [$permission->id],
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_role_fails_with_missing_fields(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->post(route('roles.store'), []);

        $response->assertSessionHasErrors(['name', 'default_route', 'permissions']);
    }

    public function test_store_role_fails_with_invalid_default_route(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $permission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->post(route('roles.store'), [
            'name' => 'TestRole',
            'default_route' => 'NonExistent.page',
            'permissions' => [$permission->id],
        ]);

        $response->assertSessionHasErrors('default_route');
    }

    public function test_role_request_rejects_default_route_without_named_route(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        // 建立一個存在於 permissions 但無對應命名路由的 permission
        Permission::factory()->create([
            'name' => 'OrphanModule.ghost',
            'module' => 'OrphanModule',
            'action' => 'ghost',
        ]);

        $existing = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->post(route('roles.store'), [
            'name' => 'OrphanRole',
            'default_route' => 'OrphanModule.ghost',
            'permissions' => [$existing->id],
        ]);

        $response->assertSessionHasErrors('default_route');
    }

    public function test_permission_session_reloads_when_role_updated_at_advances(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        // 確認原本可訪問 /roles
        $this->get(route('roles.index'))->assertStatus(200);

        // 模擬管理者拔掉 Admin 角色的 Role.index 權限（保留 default_route 對應的 Dashboard.index）
        $admin = Role::where('name', 'Admin')->first();
        $dashboard = Permission::where('name', 'Dashboard.index')->first();
        $admin->permissions()->sync([$dashboard->id]);
        $admin->forceFill(['updated_at' => now()->addMinute()])->save();

        // 同一個 session 再次請求 → middleware 應偵測 stale 並重載，導向 default_route
        $this->get(route('roles.index'))->assertRedirect(route('dashboard'));
    }

    public function test_permission_session_reloads_when_user_role_id_changes(): void
    {
        $this->seedPermissions();

        // 使用者一開始是 Admin（擁有 Role.index）
        $user = $this->createUserWithRole('Admin');
        $this->get(route('roles.index'))->assertStatus(200);

        // 建立只有 Dashboard.index 的最小角色（不可用 Viewer，Viewer 也有 Role.index）
        $dashboard = Permission::where('name', 'Dashboard.index')->first();
        $minimalRole = Role::factory()->create(['default_route' => 'Dashboard.index']);
        $minimalRole->permissions()->sync([$dashboard->id]);

        // 管理者把 user 換到最小角色
        $user->forceFill([
            'role_id' => $minimalRole->id,
            'updated_at' => now()->addMinute(),
        ])->save();

        // 同一個 session 再次請求 → middleware 應偵測 user.updated_at 已 advance 並重載
        // 新角色沒有 Role.index → 應導向 default_route
        $this->get(route('roles.index'))->assertRedirect(route('dashboard'));
    }

    public function test_edit_nonexistent_role_redirects_with_error(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->get(route('roles.edit', 99999));

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('error', '找不到該角色');
    }

    public function test_update_nonexistent_role_redirects_with_error(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $permission = Permission::where('name', 'Dashboard.index')->first();

        $response = $this->put(route('roles.update', 99999), [
            'name' => 'GhostRole',
            'default_route' => 'Dashboard.index',
            'permissions' => [$permission->id],
        ]);

        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('error', '找不到該角色');
    }

    public function test_destroy_nonexistent_role_returns_422(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->delete(route('roles.destroy', 99999));

        $response->assertStatus(422);
        $response->assertExactJson(['message' => '找不到該角色']);
    }

    public function test_viewer_cannot_perform_role_write_operations(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');

        $role = Role::where('name', 'Viewer')->first();
        $permission = Permission::where('name', 'Dashboard.index')->first();

        $payload = [
            'name' => 'Hacked',
            'default_route' => 'Dashboard.index',
            'permissions' => [$permission->id],
        ];

        // Viewer 僅有 index 權限，寫入操作應被 middleware 導向 default_route
        $this->post(route('roles.store'), $payload)->assertRedirect(route('dashboard'));
        $this->put(route('roles.update', $role), $payload)->assertRedirect(route('dashboard'));
        $this->delete(route('roles.destroy', $role))->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('roles', ['name' => 'Hacked']);
        $this->assertDatabaseHas('roles', ['name' => 'Viewer']);
    }

    public function test_store_role_fails_with_nonexistent_permission_id(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->post(route('roles.store'), [
            'name' => 'TestRole',
            'default_route' => 'Dashboard.index',
            'permissions' => [999999],
        ]);

        $response->assertSessionHasErrors('permissions.0');
        $this->assertDatabaseMissing('roles', ['name' => 'TestRole']);
    }

    public function test_delete_role_removes_pivot_rows(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $role = Role::where('name', 'Viewer')->first();
        $this->assertDatabaseHas('role_has_permissions', ['role_id' => $role->id]);

        $this->delete(route('roles.destroy', $role))->assertOk();

        $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id]);
    }
}
