<?php

namespace App\Http\Requests;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
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
            'grade_ids.*' => ['integer', Rule::exists('grades', 'id')],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }
}
