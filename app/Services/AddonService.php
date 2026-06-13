<?php

namespace App\Services;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use App\Jobs\SyncShopAddonsForGrade;
use App\Models\Addon;
use App\Repositories\AddonRepository;
use App\Repositories\GradeRepository;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AddonService
{
    public function __construct(
        private AddonRepository $addonRepository,
        private GradeRepository $gradeRepository,
    ) {}

    public function findAddonById(int $id): ?Addon
    {
        $addon = $this->addonRepository->getById($id);

        if (! $addon || $addon->status === AddonStatus::Deleted) {
            return null;
        }

        return $addon;
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{addons: LengthAwarePaginator, filters: array, perPage: int, grades: Collection}
     */
    public function getIndexData(array $filters, int $perPage): array
    {
        return [
            'addons' => $this->addonRepository->paginate($perPage, $filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'grades' => $this->gradeRepository->getAll(),
        ];
    }

    /**
     * @return array{types: AddonType[], statuses: AddonStatus[], grades: Collection}
     */
    public function getCreateData(): array
    {
        return [
            'types' => AddonType::cases(),
            'statuses' => [AddonStatus::Active, AddonStatus::Inactive],
            'grades' => $this->gradeRepository->getAll(),
        ];
    }

    /**
     * @return array{addon: Addon, types: AddonType[], statuses: AddonStatus[], grades: Collection, selectedGradeIds: int[]}
     */
    public function getEditData(Addon $addon): array
    {
        $addon->load('image', 'grades');

        return [
            'addon' => $addon,
            'types' => AddonType::cases(),
            'statuses' => [AddonStatus::Active, AddonStatus::Inactive],
            'grades' => $this->gradeRepository->getAll(),
            'selectedGradeIds' => $addon->grades->pluck('id')->all(),
        ];
    }

    /**
     * post-commit 副作用（grade sync job dispatch）於內層交易提交後立即執行，
     * 故本方法不可置於外層 DB transaction 內呼叫。
     */
    public function createAddon(array $data, ?UploadedFile $image): Addon
    {
        $gradeIds = $data['grade_ids'] ?? [];
        $addonData = array_diff_key($data, ['grade_ids' => null]);

        $addon = null;
        $affectedGradeIds = [];

        DB::transaction(function () use ($addonData, $gradeIds, $image, &$addon, &$affectedGradeIds) {
            $addon = $this->addonRepository->create($addonData);

            if ($image) {
                $url = $this->storeImage($image, $addon->id);
                $this->addonRepository->upsertImage($addon, $url);
            }

            if (! empty($gradeIds)) {
                $result = $this->addonRepository->syncGrades($addon, $gradeIds);
                $affectedGradeIds = array_merge($result['added'], $result['removed']);
            }

            if (! empty($affectedGradeIds)) {
                $addon->update(['syncing' => AddonSyncing::Syncing]);
            }
        });

        if (! empty($affectedGradeIds)) {
            $this->dispatchGradeSyncBatch($addon, $affectedGradeIds);
        }

        return $addon;
    }

    /**
     * post-commit 副作用（grade sync job dispatch）於內層交易提交後立即執行，
     * 故本方法不可置於外層 DB transaction 內呼叫。
     */
    public function updateAddon(Addon $addon, array $data, ?UploadedFile $image, bool $removeImage = false): void
    {
        $gradeIds = $data['grade_ids'] ?? [];
        $addonData = array_diff_key($data, ['grade_ids' => null]);

        $oldImageUrl = null;
        $affectedGradeIds = [];

        DB::transaction(function () use ($addon, $addonData, $gradeIds, $image, $removeImage, &$oldImageUrl, &$affectedGradeIds) {
            $oldImageUrl = $addon->image?->image_url;

            $this->addonRepository->update($addon, $addonData);

            $result = $this->addonRepository->syncGrades($addon, $gradeIds);
            $affectedGradeIds = array_merge($result['added'], $result['removed']);

            if ($image) {
                $newUrl = $this->storeImage($image, $addon->id);
                $this->addonRepository->upsertImage($addon, $newUrl);

                if ($oldImageUrl) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldImageUrl));
                }
            } elseif ($removeImage && $oldImageUrl) {
                $this->addonRepository->deleteImage($addon);
                DB::afterCommit(fn () => Storage::disk('public')->delete($oldImageUrl));
            }

            if (! empty($affectedGradeIds)) {
                $addon->update(['syncing' => AddonSyncing::Syncing]);
            }
        });

        if (! empty($affectedGradeIds)) {
            $this->dispatchGradeSyncBatch($addon, $affectedGradeIds);
        }
    }

    /**
     * post-commit 副作用（圖片刪除）於交易提交後立即執行，
     * 故本方法不可置於外層 DB transaction 內呼叫。
     */
    public function deleteAddon(Addon $addon): void
    {
        $imageUrl = $addon->image?->image_url;

        DB::transaction(fn () => $this->addonRepository->softDelete($addon));

        if ($imageUrl) {
            Storage::disk('public')->delete($imageUrl);
        }
    }

    private function storeImage(UploadedFile $image, int $addonId): string
    {
        $ext      = $image->getClientOriginalExtension();
        $filename = "{$addonId}-img-" . now()->timestamp . ".{$ext}";

        Storage::disk('public')->putFileAs('addons', $image, $filename);

        return "addons/{$filename}";
    }

    private function dispatchGradeSyncBatch(Addon $addon, array $gradeIds): void
    {
        $addonId = $addon->id;
        $jobs = $this->gradeRepository->getByIds($gradeIds)
            ->map(fn ($grade) => new SyncShopAddonsForGrade($grade))
            ->all();

        Bus::batch($jobs)
            ->name('Addon grade sync')
            ->onQueue('addon_sync')
            ->then(function (Batch $batch) use ($addonId) {
                app(AddonRepository::class)->setSyncingById($addonId, AddonSyncing::Done);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($addonId) {
                Log::error('Addon grade sync batch failed', [
                    'addon_id' => $addonId,
                    'batch_id' => $batch->id,
                    'error'    => $e->getMessage(),
                ]);
                app(AddonRepository::class)->setSyncingById($addonId, AddonSyncing::Done);
            })
            ->dispatch();
    }
}
