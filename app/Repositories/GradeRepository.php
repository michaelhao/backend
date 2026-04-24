<?php

namespace App\Repositories;

use App\Enums\GradeStatus;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Collection;

class GradeRepository
{
    public function getAll(): Collection
    {
        return Grade::orderByDesc('weight')->get();
    }

    public function findByWeight(int $weight, ?int $excludeId): ?Grade
    {
        return Grade::where('weight', $weight)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->first();
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
