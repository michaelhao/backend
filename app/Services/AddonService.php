<?php

namespace App\Services;

use App\Enums\AddonStatus;
use App\Enums\AddonSyncing;
use App\Enums\AddonType;
use App\Jobs\SyncShopAddonsForGrade;
use App\Models\Addon;
use App\Models\Grade;
use App\Repositories\AddonRepository;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AddonService
{
    public function __construct(private AddonRepository $addonRepository) {}

    /**
     * @return array{addons: LengthAwarePaginator, filters: array, perPage: int}
     */
    public function getIndexData(Request $request): array
    {
        $perPage = in_array((int) $request->per_page, [50, 100, 150, 200])
            ? (int) $request->per_page
            : 50;

        $filters = $request->only(['keyword', 'type', 'status', 'grade_id']);

        return [
            'addons' => $this->addonRepository->paginate($perPage, $filters),
            'filters' => $filters,
            'perPage' => $perPage,
            'grades' => Grade::all(),
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
            'grades' => Grade::all(),
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
            'grades' => Grade::all(),
            'selectedGradeIds' => $addon->grades->pluck('id')->all(),
        ];
    }

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
            DB::afterCommit(fn () => $this->dispatchGradeSyncBatch($addon, $affectedGradeIds));
        }

        return $addon;
    }

    public function updateAddon(Addon $addon, array $data, ?UploadedFile $image): void
    {
        $gradeIds = $data['grade_ids'] ?? [];
        $addonData = array_diff_key($data, ['grade_ids' => null]);

        $oldImageUrl = null;
        $affectedGradeIds = [];

        DB::transaction(function () use ($addon, $addonData, $gradeIds, $image, &$oldImageUrl, &$affectedGradeIds) {
            $oldImageUrl = $addon->image?->image_url;

            $this->addonRepository->update($addon, $addonData);

            $result = $this->addonRepository->syncGrades($addon, $gradeIds);
            $affectedGradeIds = array_merge($result['added'], $result['removed']);

            if ($image) {
                $newUrl = $this->storeImage($image, $addon->id);
                $this->addonRepository->upsertImage($addon, $newUrl);

                if ($oldImageUrl) {
                    $oldPath = $oldImageUrl;
                    DB::afterCommit(fn () => Storage::disk('public')->delete($oldPath));
                }
            }

            if (! empty($affectedGradeIds)) {
                $addon->update(['syncing' => AddonSyncing::Syncing]);
            }
        });

        if (! empty($affectedGradeIds)) {
            DB::afterCommit(fn () => $this->dispatchGradeSyncBatch($addon, $affectedGradeIds));
        }
    }

    public function deleteAddon(Addon $addon): void
    {
        DB::transaction(fn () => $this->addonRepository->softDelete($addon));
    }

    private function storeImage(UploadedFile $image, int $addonId): string
    {
        $ext = $image->getClientOriginalExtension();
        $filename = "{$addonId}-img-".now()->timestamp.".{$ext}";
        $path = "addons/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($image->getRealPath()));

        return $path;
    }

    private function dispatchGradeSyncBatch(Addon $addon, array $gradeIds): void
    {
        $addonId = $addon->id;
        $grades = Grade::whereIn('id', $gradeIds)->get();
        $jobs = $grades->map(fn ($grade) => new SyncShopAddonsForGrade($grade))->all();

        Bus::batch($jobs)
            ->name('Addon grade sync')
            ->then(function (Batch $batch) use ($addonId) {
                Addon::where('id', $addonId)->update(['syncing' => AddonSyncing::Done->value]);
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($addonId) {
                Addon::where('id', $addonId)->update(['syncing' => AddonSyncing::Done->value]);
            })
            ->dispatch();
    }
}
