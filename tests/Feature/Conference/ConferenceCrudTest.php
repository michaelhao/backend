<?php

namespace Tests\Feature\Conference;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ConferenceCrudTest extends TestCase
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

    private function validConferenceData(array $overrides = []): array
    {
        return array_merge([
            'name' => '測試說明會',
            'status' => ConferenceStatus::Active->value,
            'register_started_at' => Carbon::parse('2026-05-01 09:00:00')->format('Y-m-d H:i:s'),
            'register_ended_at' => Carbon::parse('2026-05-10 23:59:00')->format('Y-m-d H:i:s'),
            'started_at' => Carbon::parse('2026-05-15 10:00:00')->format('Y-m-d H:i:s'),
            'ended_at' => Carbon::parse('2026-05-15 12:00:00')->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    // ── Index ──────────────────────────────────────────────────────────────

    public function test_admin_can_access_conference_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('conferences.index'));

        $response->assertStatus(200);
    }

    public function test_viewer_can_access_conference_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('conferences.index'));

        $response->assertStatus(200);
    }

    public function test_index_filters_by_keyword(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Conference::factory()->create(['name' => '春季招商說明會']);
        Conference::factory()->create(['name' => '秋季說明會']);

        $response = $this->get(route('conferences.index', ['keyword' => '春季']));

        $response->assertStatus(200);
        $response->assertSee('春季招商說明會');
        $response->assertDontSee('秋季說明會');
    }

    public function test_index_filters_by_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Conference::factory()->active()->create(['name' => '啟用中說明會']);
        Conference::factory()->inactive()->create(['name' => '停用中說明會']);

        $response = $this->get(route('conferences.index', ['status' => '0']));

        $response->assertStatus(200);
        $response->assertSee('停用中說明會');
        $response->assertDontSee('啟用中說明會');
    }

    public function test_per_page_outside_whitelist_falls_back_to_50(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('conferences.index', ['per_page' => 9999]));

        $response->assertStatus(200);
        $this->assertSame(50, $response->viewData('perPage'));
    }

    // ── Create ─────────────────────────────────────────────────────────────

    public function test_admin_can_access_create_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('conferences.create'));

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_create_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('conferences.create'));

        $response->assertRedirect();
    }

    public function test_admin_can_create_conference(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('conferences.store'), $this->validConferenceData(['name' => '新說明會A']));

        $response->assertRedirect(route('conferences.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('conferences', ['name' => '新說明會A', 'status' => ConferenceStatus::Active->value]);
    }

    public function test_store_fails_when_name_missing(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData();
        unset($data['name']);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_fails_when_status_missing(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData();
        unset($data['status']);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_fails_when_any_time_field_missing(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        foreach (['started_at', 'ended_at', 'register_started_at', 'register_ended_at'] as $field) {
            $data = $this->validConferenceData();
            unset($data[$field]);

            $response = $this->post(route('conferences.store'), $data);

            $response->assertSessionHasErrors($field);
        }

        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_fails_when_register_ended_after_started(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData([
            'register_ended_at' => Carbon::parse('2026-05-15 11:00:00')->format('Y-m-d H:i:s'),
        ]);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertSessionHasErrors('register_ended_at');
        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_fails_when_ended_not_after_started(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData([
            'ended_at' => $this->validConferenceData()['started_at'],
        ]);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertSessionHasErrors('ended_at');
        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_fails_when_register_started_not_before_register_ended(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData([
            'register_started_at' => Carbon::parse('2026-05-10 23:59:00')->format('Y-m-d H:i:s'),
        ]);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertSessionHasErrors('register_ended_at');
        $this->assertDatabaseCount('conferences', 0);
    }

    public function test_store_accepts_register_ended_equal_to_started(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $data = $this->validConferenceData([
            'register_ended_at' => $this->validConferenceData()['started_at'],
        ]);

        $response = $this->post(route('conferences.store'), $data);

        $response->assertRedirect(route('conferences.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('conferences', 1);
    }

    public function test_store_fails_with_invalid_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('conferences.store'), $this->validConferenceData(['status' => -1]));

        $response->assertSessionHasErrors('status');
    }

    // ── Update ─────────────────────────────────────────────────────────────

    public function test_admin_can_access_edit_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $conference = Conference::factory()->create();

        $response = $this->get(route('conferences.edit', $conference));

        $response->assertStatus(200);
    }

    public function test_admin_can_update_conference(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $conference = Conference::factory()->create(['name' => '舊名稱']);

        $response = $this->put(route('conferences.update', $conference), $this->validConferenceData(['name' => '新名稱']));

        $response->assertRedirect(route('conferences.index'));
        $response->assertSessionHas('success');
        $conference->refresh();
        $this->assertEquals('新名稱', $conference->name);
    }

    public function test_edit_for_non_existing_id_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('conferences.edit', 999999));

        $response->assertRedirect(route('conferences.index'));
        $response->assertSessionHas('error');
    }

    public function test_update_for_non_existing_id_redirects_with_error(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->put(route('conferences.update', 999999), $this->validConferenceData());

        $response->assertRedirect(route('conferences.index'));
        $response->assertSessionHas('error');
    }

    public function test_viewer_cannot_access_create_store_edit_update(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $conference = Conference::factory()->create();

        $this->get(route('conferences.create'))->assertRedirect();
        $this->post(route('conferences.store'), $this->validConferenceData())->assertRedirect();
        $this->get(route('conferences.edit', $conference))->assertRedirect();
        $this->put(route('conferences.update', $conference), $this->validConferenceData())->assertRedirect();
    }

    // ── No delete guard ────────────────────────────────────────────────────

    public function test_delete_route_is_not_registered(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $conference = Conference::factory()->create();

        $response = $this->delete('/conferences/'.$conference->id);

        $this->assertSame(405, $response->getStatusCode());
    }

    public function test_conference_delete_permission_is_not_registered(): void
    {
        $this->seedPermissions();

        $this->assertFalse(Permission::where('name', 'Conference.delete')->exists());
    }
}
