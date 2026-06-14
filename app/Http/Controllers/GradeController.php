<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\GradeRequest;
use App\Services\GradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function __construct(private GradeService $gradeService) {}

    #[RequiresPermission('Grade.index')]
    public function index()
    {
        $data = $this->gradeService->getIndexData();

        return view('admin.grades.index', $data);
    }

    #[RequiresPermission('Grade.create')]
    public function create()
    {
        $data = $this->gradeService->getCreateData();

        return view('admin.grades.create', $data);
    }

    #[RequiresPermission('Grade.create')]
    public function store(GradeRequest $request)
    {
        $this->gradeService->createGrade(
            $request->safe()->only(['code', 'name', 'price', 'weight', 'status']),
        );

        return redirect()->route('grades.index')->with('success', '版本已建立');
    }

    #[RequiresPermission('Grade.update')]
    public function edit(int $id)
    {
        $grade = $this->gradeService->findGradeById($id);
        if (! $grade) {
            return redirect()->route('grades.index')->with('error', '找不到該版本');
        }

        $data = $this->gradeService->getEditData($grade);

        return view('admin.grades.edit', $data);
    }

    #[RequiresPermission('Grade.update')]
    public function update(GradeRequest $request, int $id)
    {
        $grade = $this->gradeService->findGradeById($id);
        if (! $grade) {
            return redirect()->route('grades.index')->with('error', '找不到該版本');
        }

        $this->gradeService->updateGrade(
            $grade,
            $request->safe()->only(['code', 'name', 'price', 'weight', 'status']),
        );

        return redirect()->route('grades.index')->with('success', '版本已更新');
    }

    #[RequiresPermission('Grade.update')]
    public function checkWeight(Request $request): JsonResponse
    {
        $weight = (int) $request->query('weight');
        $excludeId = $request->query('exclude_id') ? (int) $request->query('exclude_id') : null;

        return response()->json($this->gradeService->checkWeightConflict($weight, $excludeId));
    }

    #[RequiresPermission('Grade.update')]
    public function toggleStatus(int $id): JsonResponse
    {
        $grade = $this->gradeService->findGradeById($id);
        if (! $grade) {
            return response()->json(['message' => '找不到該版本'], 404);
        }

        $this->gradeService->toggleStatus($grade);

        return response()->json(['message' => '版本狀態已更新']);
    }
}
