<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
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

        $this->actingAs($user);
        $user->loadPermissionsToSession();

        return $user;
    }

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
            'password' => 'password123',
            'password_confirmation' => 'password123',
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
            'password' => 'new_password_123',
            'password_confirmation' => 'new_password_123',
            'role_id' => $role->id,
        ]);

        $response->assertRedirect(route('users.index'));
        $target->refresh();
        $this->assertTrue(Hash::check('new_password_123', $target->password));
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
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $this->seedPermissions();

        $admin = $this->createUserWithRole('Admin');

        $response = $this->delete(route('users.destroy', $admin));

        $response->assertStatus(422);
        $response->assertExactJson(['message' => '無法刪除自己的帳號']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_viewer_cannot_access_create_page(): void
    {
        $this->seedPermissions();

        $this->createUserWithRole('Viewer');

        $response = $this->get(route('users.create'));

        $response->assertRedirect();
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
            'password' => 'password123',
            'password_confirmation' => 'password123',
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
            'password' => 'password123',
            'password_confirmation' => 'different_password',
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
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_id' => 99999,
        ]);

        $response->assertSessionHasErrors('role_id');
    }
}
