<?php

namespace App\Repositories;

use App\Enums\GradeStatus;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Collection;

class GradeRepository
{
    public function getAll(): Collection
    {
        return Grade::latest()->get();
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
