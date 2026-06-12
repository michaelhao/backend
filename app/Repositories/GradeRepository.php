<?php

namespace App\Repositories;

use App\Enums\GradeStatus;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GradeRepository
{
    public function getAll(): Collection
    {
        return Grade::orderByDesc('weight')->get();
    }

    public function getById(int $id): ?Grade
    {
        return Grade::find($id);
    }

    public function findByWeight(int $weight, ?int $excludeId): ?Grade
    {
        return Grade::where('weight', $weight)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->first();
    }

    /**
     * @return int[] Addon IDs attached to the grade via grades_addons.
     */
    public function getAddonIdsForGrade(int $gradeId): array
    {
        return DB::table('grades_addons')
            ->where('grade_id', $gradeId)
            ->pluck('addon_id')
            ->all();
    }

    public function create(array $data): Grade
    {
        return Grade::create($data);
    }

    public function update(Grade $grade, array $data): void
    {
        $grade->update($data);
    }

    public function toggleStatus(Grade $grade): void
    {
        $grade->update([
            'status' => $grade->status === GradeStatus::Active
                ? GradeStatus::Inactive
                : GradeStatus::Active,
        ]);
    }
}
