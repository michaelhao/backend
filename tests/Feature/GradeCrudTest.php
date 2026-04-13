<?php

namespace Tests\Feature;

use App\Enums\GradeStatus;
use App\Models\Grade;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCrudTest extends TestCase
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

    public function test_admin_can_access_grade_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('grades.index'));

        $response->assertStatus(200);
    }

    public function test_admin_can_create_grade(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_test',
            'name'   => '測試版本',
            'price'  => 5000,
            'status' => 1,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('grades', [
            'code'  => 'grade_test',
            'name'  => '測試版本',
            'price' => 5000,
        ]);
    }

    public function test_admin_can_edit_grade(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();

        $response = $this->put(route('grades.update', $grade), [
            'code'   => 'grade_updated',
            'name'   => '更新版本',
            'price'  => 8888,
            'status' => 0,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('success');
        $grade->refresh();
        $this->assertEquals('grade_updated', $grade->code);
        $this->assertEquals('更新版本', $grade->name);
        $this->assertEquals(8888, $grade->price);
        $this->assertEquals(GradeStatus::Inactive, $grade->status);
    }

    public function test_edit_form_loads_with_existing_values(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create([
            'code'  => 'grade_show',
            'name'  => '顯示版本',
            'price' => 3000,
        ]);

        $response = $this->get(route('grades.edit', $grade));

        $response->assertStatus(200);
        $response->assertSee('grade_show');
        $response->assertSee('顯示版本');
        $response->assertSee('3000');
    }

    public function test_update_allows_same_code_and_name_for_self(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['code' => 'mycode', 'name' => 'myname']);

        $response = $this->put(route('grades.update', $grade), [
            'code'   => 'mycode',
            'name'   => 'myname',
            'price'  => 9999,
            'status' => 1,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_store_fails_with_duplicate_code(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Grade::factory()->create(['code' => 'dupe_code']);

        $response = $this->post(route('grades.store'), [
            'code'   => 'dupe_code',
            'name'   => '不同名稱',
            'price'  => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_fails_with_duplicate_name(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Grade::factory()->create(['name' => '重複名稱']);

        $response = $this->post(route('grades.store'), [
            'code'   => 'uniquecode',
            'name'   => '重複名稱',
            'price'  => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_fails_with_invalid_code_characters(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade!@#',
            'name'   => '合法名稱',
            'price'  => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_fails_with_invalid_name_characters(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'validcode',
            'name'   => '非法!@#名稱',
            'price'  => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_fails_with_price_too_low(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_low',
            'name'   => '低價版本',
            'price'  => 1,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_store_fails_with_invalid_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_bad',
            'name'   => '壞狀態版本',
            'price'  => 2000,
            'status' => 99,
        ]);

        $response->assertSessionHasErrors('status');
    }

    public function test_admin_can_toggle_active_grade_to_inactive(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['status' => GradeStatus::Active]);

        $response = $this->patch(route('grades.toggle', $grade));

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('success');
        $grade->refresh();
        $this->assertEquals(GradeStatus::Inactive, $grade->status);
    }

    public function test_admin_can_toggle_inactive_grade_to_active(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['status' => GradeStatus::Inactive]);

        $response = $this->patch(route('grades.toggle', $grade));

        $response->assertRedirect(route('grades.index'));
        $grade->refresh();
        $this->assertEquals(GradeStatus::Active, $grade->status);
    }

    public function test_viewer_cannot_toggle_grade_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $grade = Grade::factory()->create();

        $response = $this->patch(route('grades.toggle', $grade));

        $response->assertRedirect();
    }

    public function test_viewer_cannot_access_create_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('grades.create'));

        $response->assertRedirect();
    }

    public function test_delete_route_does_not_exist(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();

        $response = $this->delete("/grades/{$grade->id}");

        $response->assertStatus(405);
    }
}
