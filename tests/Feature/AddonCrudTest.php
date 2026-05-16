<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use App\Enums\GradeStatus;
use App\Enums\ShopAddonSource;
use App\Enums\ShopAddonStatus;
use App\Jobs\SyncShopAddonsForGrade;
use App\Models\Addon;
use App\Models\AddonImage;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopAdmin;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AddonCrudTest extends TestCase
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

    private function validAddonData(array $overrides = []): array
    {
        return array_merge([
            'type' => AddonType::Feature->value,
            'name' => '測試功能',
            'price' => 5000,
            'unit' => null,
            'status' => AddonStatus::Active->value,
        ], $overrides);
    }

    // ── Index ──────────────────────────────────────────────────────────────

    public function test_admin_can_access_addon_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('addons.index'));

        $response->assertStatus(200);
    }

    public function test_viewer_can_access_addon_index(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('addons.index'));

        $response->assertStatus(200);
    }

    public function test_index_excludes_deleted_addons(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        Addon::factory()->deleted()->create(['name' => '已刪除功能']);

        $response = $this->get(route('addons.index'));

        $response->assertStatus(200);
        $response->assertDontSee('已刪除功能');
    }

    // ── Create ─────────────────────────────────────────────────────────────

    public function test_admin_can_access_create_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->get(route('addons.create'));

        $response->assertStatus(200);
    }

    public function test_viewer_cannot_access_create_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');

        $response = $this->get(route('addons.create'));

        $response->assertRedirect();
    }

    public function test_admin_can_create_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('addons.store'), $this->validAddonData(['name' => '新功能A']));

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('addons', ['name' => '新功能A', 'price' => 5000]);
    }

    public function test_create_with_grade_ids_creates_grades_addons_rows(): void
    {
        Bus::fake();
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();

        $this->post(route('addons.store'), $this->validAddonData([
            'name' => '版本功能',
            'grade_ids' => [$grade->id],
        ]));

        $addon = Addon::where('name', '版本功能')->firstOrFail();
        $this->assertDatabaseHas('grades_addons', ['grade_id' => $grade->id, 'addon_id' => $addon->id]);
    }

    public function test_create_with_grade_ids_dispatches_sync_batch(): void
    {
        Bus::fake();
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();

        $this->post(route('addons.store'), $this->validAddonData([
            'grade_ids' => [$grade->id],
        ]));

        Bus::assertBatched(fn ($batch) => $batch->jobs->contains(
            fn ($job) => $job instanceof SyncShopAddonsForGrade
        ));
    }

    public function test_store_fails_when_grade_ids_contains_inactive_grade(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $inactiveGrade = Grade::factory()->create(['status' => GradeStatus::Inactive]);

        $response = $this->post(route('addons.store'), $this->validAddonData([
            'name' => '不可建立',
            'grade_ids' => [$inactiveGrade->id],
        ]));

        $response->assertSessionHasErrors('grade_ids.0');
        $this->assertDatabaseMissing('addons', ['name' => '不可建立']);
    }

    public function test_update_allows_keeping_existing_inactive_grade_link(): void
    {
        Bus::fake();
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        DB::table('grades_addons')->insert([
            'grade_id' => $grade->id,
            'addon_id' => $addon->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $grade->update(['status' => GradeStatus::Inactive]);

        $response = $this->put(route('addons.update', $addon), $this->validAddonData([
            'name' => '只改名稱',
            'grade_ids' => [$grade->id],
        ]));

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('addons', ['id' => $addon->id, 'name' => '只改名稱']);
        $this->assertDatabaseHas('grades_addons', ['grade_id' => $grade->id, 'addon_id' => $addon->id]);
    }

    public function test_store_fails_with_invalid_type(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('addons.store'), $this->validAddonData(['type' => 99]));

        $response->assertSessionHasErrors('type');
    }

    public function test_store_fails_with_deleted_status(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('addons.store'), $this->validAddonData(['status' => AddonStatus::Deleted->value]));

        $response->assertSessionHasErrors('status');
    }

    public function test_store_fails_with_gif_image(): void
    {
        Storage::fake('public');
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $response = $this->post(route('addons.store'), array_merge(
            $this->validAddonData(),
            ['image' => UploadedFile::fake()->create('test.gif', 100, 'image/gif')]
        ));

        $response->assertSessionHasErrors('image');
    }

    public function test_store_accepts_jpg_image(): void
    {
        Storage::fake('public');
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        // Minimal valid JPEG (1×1 pixel) without requiring GD extension
        $jpegContent = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'.
            'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAAR'.
            'CAABAAEDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABgQF/8QAIBAAAQQCAgMAAAAAAAAAAAAAAQIDBBExBSFB'.
            'Yf/EABUBAQEAAAAAAAAAAAAAAAAAAAAB/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8Anr2xWXW7HF'.
            'MsJkMpFqiB4sJiJeHxmCj2v5uqSaNrR//2Q=='
        );

        $tmpPath = sys_get_temp_dir().'/test_addon_'.uniqid().'.jpg';
        file_put_contents($tmpPath, $jpegContent);
        $file = new UploadedFile($tmpPath, 'test.jpg', 'image/jpeg', null, true);

        $response = $this->post(route('addons.store'), array_merge(
            $this->validAddonData(['name' => '圖片功能']),
            ['image' => $file]
        ));

        @unlink($tmpPath);

        $response->assertRedirect(route('addons.index'));
        $addon = Addon::where('name', '圖片功能')->firstOrFail();
        $this->assertDatabaseHas('addons_image', ['addon_id' => $addon->id]);
    }

    // ── Update ─────────────────────────────────────────────────────────────

    public function test_admin_can_access_edit_page(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $addon = Addon::factory()->create();

        $response = $this->get(route('addons.edit', $addon));

        $response->assertStatus(200);
    }

    public function test_admin_can_update_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $addon = Addon::factory()->create(['name' => '舊名稱']);

        $response = $this->put(route('addons.update', $addon), $this->validAddonData(['name' => '新名稱']));

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHas('success');
        $addon->refresh();
        $this->assertEquals('新名稱', $addon->name);
    }

    public function test_update_can_remove_existing_image(): void
    {
        Storage::fake('public');
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $addon = Addon::factory()->create();
        $imagePath = "addons/{$addon->id}-img-existing.jpg";
        Storage::disk('public')->put($imagePath, 'dummy');
        AddonImage::create(['addon_id' => $addon->id, 'image_url' => $imagePath]);

        $response = $this->put(route('addons.update', $addon), $this->validAddonData(['remove_image' => 1]));

        $response->assertRedirect(route('addons.index'));
        $this->assertDatabaseMissing('addons_image', ['addon_id' => $addon->id]);
        Storage::disk('public')->assertMissing($imagePath);
    }

    public function test_update_with_new_image_overrides_remove_image(): void
    {
        Storage::fake('public');
        $this->seedPermissions();
        $this->createUserWithRole('Admin');

        $addon = Addon::factory()->create();
        $oldPath = "addons/{$addon->id}-img-old.jpg";
        Storage::disk('public')->put($oldPath, 'dummy');
        AddonImage::create(['addon_id' => $addon->id, 'image_url' => $oldPath]);

        $jpegContent = base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'.
            'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAAR'.
            'CAABAAEDASIAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABgQF/8QAIBAAAQQCAgMAAAAAAAAAAAAAAQIDBBExBSFB'.
            'Yf/EABUBAQEAAAAAAAAAAAAAAAAAAAAB/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8Anr2xWXW7HF'.
            'MsJkMpFqiB4sJiJeHxmCj2v5uqSaNrR//2Q=='
        );
        $tmpPath = sys_get_temp_dir().'/test_addon_'.uniqid().'.jpg';
        file_put_contents($tmpPath, $jpegContent);
        $file = new UploadedFile($tmpPath, 'new.jpg', 'image/jpeg', null, true);

        $response = $this->put(route('addons.update', $addon), array_merge(
            $this->validAddonData(['remove_image' => 1]),
            ['image' => $file],
        ));

        @unlink($tmpPath);

        $response->assertRedirect(route('addons.index'));
        $this->assertDatabaseHas('addons_image', ['addon_id' => $addon->id]);
        $image = AddonImage::where('addon_id', $addon->id)->firstOrFail();
        $this->assertNotEquals($oldPath, $image->image_url);
    }

    public function test_update_with_changed_grade_ids_dispatches_sync_batch(): void
    {
        Bus::fake();
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();

        $this->put(route('addons.update', $addon), $this->validAddonData([
            'grade_ids' => [$grade->id],
        ]));

        Bus::assertBatched(fn ($batch) => $batch->jobs->contains(
            fn ($job) => $job instanceof SyncShopAddonsForGrade
        ));
    }

    public function test_update_with_changed_grade_ids_sets_syncing(): void
    {
        Bus::fake();
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();

        $this->put(route('addons.update', $addon), $this->validAddonData([
            'grade_ids' => [$grade->id],
        ]));

        $addon->refresh();
        $this->assertEquals(AddonSyncing::Syncing, $addon->syncing);
    }

    public function test_cannot_access_edit_page_for_deleted_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $addon = Addon::factory()->deleted()->create();

        $response = $this->get(route('addons.edit', $addon));

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHas('error');
    }

    public function test_cannot_update_deleted_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $addon = Addon::factory()->deleted()->create();

        $response = $this->put(route('addons.update', $addon), $this->validAddonData());

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHas('error');
    }

    public function test_viewer_cannot_update_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $addon = Addon::factory()->create();

        $response = $this->put(route('addons.update', $addon), $this->validAddonData());

        $response->assertRedirect();
    }

    // ── Delete ─────────────────────────────────────────────────────────────

    public function test_admin_can_soft_delete_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $addon = Addon::factory()->create();

        $response = $this->delete(route('addons.destroy', $addon));

        $response->assertOk();
        $response->assertJson(['message' => '附加功能已刪除']);
        $addon->refresh();
        $this->assertEquals(AddonStatus::Deleted, $addon->status);
    }

    public function test_delete_removes_grades_addons_rows(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $addon = Addon::factory()->create();
        DB::table('grades_addons')->insert([
            'grade_id' => $grade->id,
            'addon_id' => $addon->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete(route('addons.destroy', $addon));

        $this->assertDatabaseMissing('grades_addons', ['addon_id' => $addon->id]);
    }

    public function test_delete_removes_shops_addons_rows(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Admin');
        $grade = Grade::factory()->create();
        $shop = Shop::factory()->create(['grade_id' => $grade->id]);
        ShopAdmin::factory()->create(['shop_id' => $shop->id]);
        $addon = Addon::factory()->create();
        DB::table('shops_addons')->insert([
            'shop_id' => $shop->id,
            'addon_id' => $addon->id,
            'source' => ShopAddonSource::Grade->value,
            'status' => ShopAddonStatus::Enabled->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->delete(route('addons.destroy', $addon));

        $this->assertDatabaseMissing('shops_addons', ['addon_id' => $addon->id]);
    }

    public function test_viewer_cannot_delete_addon(): void
    {
        $this->seedPermissions();
        $this->createUserWithRole('Viewer');
        $addon = Addon::factory()->create();

        $response = $this->delete(route('addons.destroy', $addon));

        $response->assertRedirect();
        $addon->refresh();
        $this->assertNotEquals(AddonStatus::Deleted, $addon->status);
    }
}
