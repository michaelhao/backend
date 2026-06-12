<?php

namespace App\Repositories;

use App\Enums\AddonStatus;
use App\Models\Addon;
use App\Models\AddonImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AddonRepository
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return Addon::query()
            ->where('status', '!=', AddonStatus::Deleted->value)
            ->with(['image', 'grades'])
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            })
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['grade_id'] ?? null, fn ($q, $v) => $q->whereHas('grades', fn ($q2) => $q2->where('grades.id', $v)))
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function getById(int $id): ?Addon
    {
        return Addon::find($id);
    }

    public function getByIdOrFail(int $id): Addon
    {
        return Addon::findOrFail($id);
    }

    /**
     * @param  string[]  $columns
     */
    public function getAllOrderedByName(array $columns = ['*']): Collection
    {
        return Addon::orderBy('name')->get($columns);
    }

    public function create(array $data): Addon
    {
        return Addon::create($data);
    }

    public function update(Addon $addon, array $data): void
    {
        $addon->update($data);
    }

    /**
     * Sync grade associations. Returns added and removed grade IDs.
     *
     * @param  int[]  $gradeIds
     * @return array{added: int[], removed: int[]}
     */
    public function syncGrades(Addon $addon, array $gradeIds): array
    {
        $result = $addon->grades()->sync($gradeIds);

        return [
            'added' => $result['attached'],
            'removed' => $result['detached'],
        ];
    }

    /**
     * Soft delete the addon and cascade-delete pivot rows.
     * Caller must wrap in DB::transaction().
     */
    public function softDelete(Addon $addon): void
    {
        $addon->update(['status' => AddonStatus::Deleted]);
        DB::table('grades_addons')->where('addon_id', $addon->id)->delete();
        DB::table('shops_addons')->where('addon_id', $addon->id)->delete();
        DB::table('addons_image')->where('addon_id', $addon->id)->delete();
    }

    public function upsertImage(Addon $addon, string $imageUrl): void
    {
        AddonImage::updateOrCreate(
            ['addon_id' => $addon->id],
            ['image_url' => $imageUrl],
        );
    }

    public function deleteImage(Addon $addon): void
    {
        DB::table('addons_image')->where('addon_id', $addon->id)->delete();
    }
}
