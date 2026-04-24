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

        $otherPermission = Permission::where('name', 'Post.index')->first();
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
        $otherPermission = Permission::where('name', 'Post.index')->first();
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
}
