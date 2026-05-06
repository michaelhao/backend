<?php

namespace App\Http\Requests;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\GradeStatus;
use App\Models\Addon;
use App\Models\Grade;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class AddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(AddonType::class)],
            'name' => ['required', 'string', 'max:50'],
            'price' => ['required', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:10'],
            'status' => ['required', Rule::in([AddonStatus::Active->value, AddonStatus::Inactive->value])],
            'grade_ids' => ['nullable', 'array'],
            'grade_ids.*' => ['integer', 'exists:grades,id', $this->gradeMustBeActiveUnlessAlreadyLinkedRule()],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * 新關聯到 addon 的版本必須為 Active;若該版本已關聯到當前編輯的 addon,允許保留(避免 update 時誤擋)。
     */
    private function gradeMustBeActiveUnlessAlreadyLinkedRule(): Closure
    {
        $existingGradeIds = $this->existingAddonGradeIds();

        return function (string $attribute, mixed $value, Closure $fail) use ($existingGradeIds): void {
            $gradeId = (int) $value;
            if (in_array($gradeId, $existingGradeIds, true)) {
                return;
            }
            $grade = Grade::find($gradeId);
            if ($grade && $grade->status !== GradeStatus::Active) {
                $fail('所選版本已停用，無法新增關聯。');
            }
        };
    }

    /** @return list<int> */
    private function existingAddonGradeIds(): array
    {
        $addonId = $this->route('id');
        if (! $addonId) {
            return [];
        }

        return Addon::find($addonId)?->grades()->pluck('grades.id')->all() ?? [];
    }
}
