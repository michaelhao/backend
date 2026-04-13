<?php

namespace App\Services;

use App\Models\Grade;
use App\Repositories\GradeRepository;
use Illuminate\Database\Eloquent\Collection;

class GradeService
{
    public function __construct(private GradeRepository $gradeRepository) {}

    /**
     * @return array{grades: Collection}
     */
    public function getIndexData(): array
    {
        return [
            'grades' => $this->gradeRepository->getAll(),
        ];
    }

    public function getCreateData(): array
    {
        return [];
    }

    /**
     * @return array{grade: Grade}
     */
    public function getEditData(Grade $grade): array
    {
        return [
            'grade' => $grade,
        ];
    }

    public function createGrade(array $data): Grade
    {
        return $this->gradeRepository->create($data);
    }

    public function updateGrade(Grade $grade, array $data): void
    {
        $this->gradeRepository->update($grade, $data);
    }

    public function toggleStatus(Grade $grade): void
    {
        $this->gradeRepository->toggleStatus($grade);
    }
}
