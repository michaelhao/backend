<?php

namespace App\Services;

use App\Models\Grade;
use App\Repositories\GradeRepository;
use Illuminate\Database\Eloquent\Collection;

class GradeService
{
    public function __construct(private GradeRepository $gradeRepository) {}

    public function getAllGrades(): Collection
    {
        return $this->gradeRepository->getAll();
    }

    /**
     * @return array{grades: Collection}
     */
    public function getIndexData(): array
    {
        return [
            'grades' => $this->getAllGrades(),
        ];
    }

    /**
     * @return array{grades: Collection}
     */
    public function getCreateData(): array
    {
        return [
            'grades' => $this->getAllGrades(),
        ];
    }

    /**
     * @return array{grade: Grade, grades: Collection}
     */
    public function getEditData(Grade $grade): array
    {
        return [
            'grade'  => $grade,
            'grades' => $this->getAllGrades(),
        ];
    }

    public function findGradeById(int $id): ?Grade
    {
        return $this->gradeRepository->getById($id);
    }

    public function findByWeight(int $weight, ?int $excludeId): ?Grade
    {
        return $this->gradeRepository->findByWeight($weight, $excludeId);
    }

    /**
     * @return array{duplicate: bool, conflicting_grade: ?array{id: int, name: string, weight: int}, grades: array<int, array{id: int, name: string, weight: int}>}
     */
    public function checkWeightConflict(int $weight, ?int $excludeId): array
    {
        if ($weight < 1) {
            return ['duplicate' => false, 'conflicting_grade' => null, 'grades' => []];
        }

        $conflict = $this->findByWeight($weight, $excludeId);

        return [
            'duplicate' => $conflict !== null,
            'conflicting_grade' => $conflict
                ? ['id' => $conflict->id, 'name' => $conflict->name, 'weight' => $conflict->weight]
                : null,
            'grades' => $this->getAllGrades()
                ->map(fn (Grade $g) => ['id' => $g->id, 'name' => $g->name, 'weight' => $g->weight])
                ->all(),
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
