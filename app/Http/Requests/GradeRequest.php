<?php

namespace App\Http\Requests;

use App\Enums\GradeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class GradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $gradeId = $this->route('grade')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u',
                Rule::unique('grades', 'code')->ignore($gradeId),
            ],
            'name' => [
                'required',
                'string',
                'max:30',
                'regex:/^[\x{4e00}-\x{9fa5}a-zA-Z0-9_]+$/u',
                Rule::unique('grades', 'name')->ignore($gradeId),
            ],
            'price' => ['required', 'integer', 'min:2'],
            'status' => ['required', new Enum(GradeStatus::class)],
        ];
    }
}
