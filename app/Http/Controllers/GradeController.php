<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\GradeRequest;
use App\Models\Grade;
use App\Services\GradeService;

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
        return view('admin.grades.create');
    }

    #[RequiresPermission('Grade.create')]
    public function store(GradeRequest $request)
    {
        $this->gradeService->createGrade(
            $request->safe()->only(['code', 'name', 'price', 'status']),
        );

        return redirect()->route('grades.index')->with('success', '版本已建立');
    }

    #[RequiresPermission('Grade.update')]
    public function edit($id)
    {
        $grade = Grade::find($id);
        if (!$grade) {
            return redirect()->route('grades.index')->with('error', '找不到該方案');
        }

        $data = $this->gradeService->getEditData($grade);

        return view('admin.grades.edit', $data);
    }

    #[RequiresPermission('Grade.update')]
    public function update(GradeRequest $request, $id)
    {
        $grade = Grade::find($id);
        if (!$grade) {
            return redirect()->route('grades.index')->with('error', '找不到該方案');
        }

        $this->gradeService->updateGrade(
            $grade,
            $request->safe()->only(['code', 'name', 'price', 'status']),
        );

        return redirect()->route('grades.index')->with('success', '版本已更新');
    }

    #[RequiresPermission('Grade.update')]
    public function toggleStatus($id)
    {
        $grade = Grade::find($id);
        if (!$grade) {
            return redirect()->route('grades.index')->with('error', '找不到該方案');
        }

        $this->gradeService->toggleStatus($grade);

        return redirect()->route('grades.index')->with('success', '版本狀態已更新');
    }
}
