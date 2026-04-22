<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.type' => ['required', 'integer', 'in:1,2,3'],
            'details.*.grade_id' => ['nullable', 'integer', 'exists:grades,id', 'required_if:details.*.type,1,2'],
            'details.*.addon_id' => ['nullable', 'integer', 'exists:addons,id', 'required_if:details.*.type,3'],
            'details.*.payment_type' => ['nullable', 'integer', 'in:1,2,3'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.name' => ['required', 'string', 'max:100'],
            'details.*.start_at' => ['required', 'date', 'after_or_equal:today'],
            'details.*.total_months' => ['required', 'integer', 'min:0', 'max:36'],
            'details.*.memo' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'integer', 'in:1,2,3'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_id' => ['nullable', 'integer', 'exists:bills_discount,id', 'required_with:discount_amount'],
        ];
    }
}
