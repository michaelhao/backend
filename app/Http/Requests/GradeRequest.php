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
        $gradeId = $this->route('id');

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
            'price'  => ['required', 'integer', 'min:2'],
            'weight' => ['required', 'integer', 'min:1', Rule::unique('grades', 'weight')->ignore($gradeId)],
            'status' => ['required', new Enum(GradeStatus::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'code'   => '代碼',
            'name'   => '名稱',
            'price'  => '價格',
            'weight' => '版本權重',
            'status' => '狀態',
        ];
    }

    public function messages(): array
    {
        return [
            'required'    => ':attribute 為必填',
            'string'      => ':attribute 必須為文字',
            'integer'     => ':attribute 必須為整數',
            'regex'       => ':attribute 僅限中英數與底線',
            'unique'      => ':attribute 已被使用',
            'code.max'    => ':attribute 不可超過 :max 個字元',
            'name.max'    => ':attribute 不可超過 :max 個字元',
            'price.min'   => ':attribute 不可小於 :min',
            'weight.min'  => ':attribute 不可小於 :min',
            'status.enum' => ':attribute 的值不正確',
        ];
    }
}
