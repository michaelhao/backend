<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_access_user_index(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->get(route('users.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!P@ssword',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role_id' => $role->id,
        ]);
    }

    public function test_admin_can_edit_user_without_changing_password(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create([
            'role_id' => $role->id,
            'password' => 'original_password',
        ]);
        $originalHash = $target->password;

        $response = $this->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertEquals('Updated Name', $target->name);
        $this->assertEquals($originalHash, $target->password);
    }

    public function test_admin_can_edit_user_with_new_password(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!P@ssword',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertTrue(Hash::check('Str0ng!P@ssword', $target->password));
    }

    public function test_edit_form_loads_with_existing_values(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@edit.com',
            'role_id' => $role->id,
        ]);

        $response = $this->get(route('users.edit', $target));

        $response->assertStatus(200);
        $response->assertSee('Test User');
        $response->assertSee('test@edit.com');
    }

    public function test_admin_can_delete_other_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->delete(route('users.destroy', $target));

        $response->assertOk();
        $response->assertExactJson(['message' => '使用者已刪除']);
        $this->assertModelMissing($target);
    }

    public function test_destroy_returns_404_for_missing_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->delete(route('users.destroy', 99999));

        $response->assertStatus(404);
        $response->assertExactJson(['message' => '找不到該使用者']);
    }

    public function test_deleting_user_removes_their_sessions(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        DB::table('sessions')->insert([
            'id' => 'target-session-id',
            'user_id' => $target->id,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        $response = $this->delete(route('users.destroy', $target));

        $response->assertOk();
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
    }

    public function test_cannot_delete_user_referenced_by_bills(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);
        Bill::factory()->create(['shop_sales_id' => $target->id]);

        $response = $this->delete(route('users.destroy', $target));

        $response->assertStatus(422);
        $response->assertExactJson(['message' => '該使用者為帳單業務，無法刪除']);
        $this->assertModelExists($target);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->seedPermissions();

        $admin = $this->createUserWithRole('Admin');

        $response = $this->delete(route('users.destroy', $admin));

        $response->assertStatus(422);
        $response->assertExactJson(['message' => '無法刪除自己的帳號']);
        $this->assertModelExists($admin);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $this->seedPermissions();

        $admin = $this->createUserWithRole('Admin');
        $viewerRole = Role::where('name', 'Viewer')->firstOrFail();

        $response = $this->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $viewerRole->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', '無法修改自己的角色');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role_id' => $admin->role_id]);
    }

    public function test_admin_can_edit_own_name_without_changing_role(): void
    {
        $this->seedPermissions();

        $admin = $this->createUserWithRole('Admin');

        $response = $this->put(route('users.update', $admin), [
            'name' => 'Updated Admin Name',
            'email' => $admin->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $admin->role_id,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'name' => 'Updated Admin Name']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('users.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_viewer_cannot_access_create_page(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');

        $response = $this->get(route('users.create'));

        $response->assertRedirect();
    }

    public function test_viewer_cannot_update_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->put(route('users.update', $target), [
            'name' => 'Hacked Name',
            'email' => $target->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $target->id, 'name' => 'Hacked Name']);
    }

    public function test_viewer_cannot_delete_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->delete(route('users.destroy', $target));

        $response->assertRedirect();
        $this->assertModelExists($target);
    }

    public function test_edit_redirects_with_error_for_missing_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->get(route('users.edit', 99999));

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('error', '找不到該使用者');
    }

    public function test_update_redirects_with_error_for_missing_user(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->put(route('users.update', 99999), [
            'name' => 'Ghost',
            'email' => 'ghost@example.com',
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $response->assertSessionHas('error', '找不到該使用者');
    }

    public function test_store_fails_with_weak_password(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => 'Weak Password',
            'email' => 'weak@example.com',
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_update_fails_with_weak_password(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'alllowercase',
            'password_confirmation' => 'alllowercase',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_store_fails_with_too_long_name(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => str_repeat('a', 101),
            'email' => 'longname@example.com',
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!P@ssword',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_fails_with_duplicate_email(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $existing = User::factory()->create();
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => 'Duplicate',
            'email' => $existing->email,
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!P@ssword',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_store_fails_without_password(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => 'No Password',
            'email' => 'nopass@example.com',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_store_fails_with_password_mismatch(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();

        $response = $this->post(route('users.store'), [
            'name' => 'Mismatch',
            'email' => 'mismatch@example.com',
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!Different',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_update_fails_with_duplicate_email(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');
        $role = Role::where('name', 'Viewer')->first();
        $existing = User::factory()->create(['role_id' => $role->id]);
        $target = User::factory()->create(['role_id' => $role->id]);

        $response = $this->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $existing->email,
            'password' => '',
            'password_confirmation' => '',
            'role_id' => $role->id,
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_store_fails_with_invalid_role_id(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Admin');

        $response = $this->post(route('users.store'), [
            'name' => 'Invalid Role',
            'email' => 'invalid-role@example.com',
            'password' => 'Str0ng!P@ssword',
            'password_confirmation' => 'Str0ng!P@ssword',
            'role_id' => 99999,
        ]);

        $response->assertSessionHasErrors('role_id');
    }
}
