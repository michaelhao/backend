<?php

namespace Tests\Feature;

use App\Enums\GradeStatus;
use App\Models\Grade;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeCruTest extends TestCase
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
            'weight' => 50,
            'status' => 1,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('grades', [
            'code' => 'grade_test',
            'name' => '測試版本',
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
            'weight' => $grade->weight,
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
            'code' => 'grade_show',
            'name' => '顯示版本',
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
            'weight' => $grade->weight,
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
            'code' => 'dupe_code',
            'name' => '不同名稱',
            'price' => 2000,
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
            'code' => 'uniquecode',
            'name' => '重複名稱',
            'price' => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_fails_with_duplicate_weight(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Grade::factory()->create(['weight' => 77]);

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_dupe_w',
            'name'   => '權重重複',
            'price'  => 2000,
            'weight' => 77,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('weight');
    }

    public function test_update_allows_same_weight_for_self(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['weight' => 88]);

        $response = $this->put(route('grades.update', $grade), [
            'code'   => $grade->code,
            'name'   => $grade->name,
            'price'  => $grade->price,
            'weight' => 88,
            'status' => $grade->status->value,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHasNoErrors();
    }

    public function test_store_fails_with_invalid_code_characters(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code' => 'grade!@#',
            'name' => '合法名稱',
            'price' => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_store_fails_with_invalid_name_characters(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code' => 'validcode',
            'name' => '非法!@#名稱',
            'price' => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_store_fails_with_price_too_low(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code' => 'grade_low',
            'name' => '低價版本',
            'price' => 1,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_store_fails_with_invalid_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code' => 'grade_bad',
            'name' => '壞狀態版本',
            'price' => 2000,
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

        $response->assertOk();
        $grade->refresh();
        $this->assertEquals(GradeStatus::Inactive, $grade->status);
    }

    public function test_admin_can_toggle_inactive_grade_to_active(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['status' => GradeStatus::Inactive]);

        $response = $this->patch(route('grades.toggle', $grade));

        $response->assertOk();
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

    public function test_edit_nonexistent_grade_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('grades.edit', ['id' => 9999]));

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('error', '找不到該版本');
    }

    public function test_update_nonexistent_grade_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->put(route('grades.update', ['id' => 9999]), [
            'code'   => 'grade_ghost',
            'name'   => '幽靈版本',
            'price'  => 2000,
            'weight' => 66,
            'status' => 1,
        ]);

        $response->assertRedirect(route('grades.index'));
        $response->assertSessionHas('error', '找不到該版本');
    }

    public function test_toggle_nonexistent_grade_returns_422(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->patch(route('grades.toggle', ['id' => 9999]));

        $response->assertStatus(422);
        $response->assertJson(['message' => '找不到該版本']);
    }

    public function test_check_weight_returns_duplicate_for_taken_weight(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['weight' => 77]);

        $response = $this->getJson(route('grades.check-weight', ['weight' => 77]));

        $response->assertOk();
        $response->assertJson([
            'duplicate' => true,
            'conflicting_grade' => [
                'id'     => $grade->id,
                'name'   => $grade->name,
                'weight' => 77,
            ],
        ]);
    }

    public function test_check_weight_returns_no_duplicate_for_unused_weight(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['weight' => 77]);

        $response = $this->getJson(route('grades.check-weight', ['weight' => 78]));

        $response->assertOk();
        $response->assertJson([
            'duplicate' => false,
            'conflicting_grade' => null,
        ]);
        $response->assertJsonFragment(['id' => $grade->id, 'name' => $grade->name, 'weight' => 77]);
    }

    public function test_check_weight_excludes_self_with_exclude_id(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create(['weight' => 77]);

        $response = $this->getJson(route('grades.check-weight', [
            'weight'     => 77,
            'exclude_id' => $grade->id,
        ]));

        $response->assertOk();
        $response->assertJson([
            'duplicate' => false,
            'conflicting_grade' => null,
        ]);
    }

    public function test_check_weight_below_one_returns_empty(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Grade::factory()->create(['weight' => 77]);

        $response = $this->getJson(route('grades.check-weight', ['weight' => 0]));

        $response->assertOk();
        $response->assertExactJson([
            'duplicate' => false,
            'conflicting_grade' => null,
            'grades' => [],
        ]);
    }

    public function test_store_fails_without_weight(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_noweight',
            'name'   => '無權重版本',
            'price'  => 2000,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('weight');
    }

    public function test_store_fails_with_weight_zero(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_zero_w',
            'name'   => '零權重版本',
            'price'  => 2000,
            'weight' => 0,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('weight');
    }

    public function test_store_fails_with_negative_price(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('grades.store'), [
            'code'   => 'grade_neg',
            'name'   => '負價版本',
            'price'  => -100,
            'weight' => 60,
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('price');
    }

    public function test_index_lists_grades_by_weight_descending(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $low = Grade::factory()->create(['name' => '低權重', 'weight' => 10]);
        $high = Grade::factory()->create(['name' => '高權重', 'weight' => 90]);
        $mid = Grade::factory()->create(['name' => '中權重', 'weight' => 50]);

        $response = $this->get(route('grades.index'));

        $response->assertOk();
        $response->assertSeeInOrder([$high->name, $mid->name, $low->name]);
    }

    public function test_viewer_cannot_check_weight(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('grades.check-weight', ['weight' => 77]));

        $response->assertRedirect();
    }
}
