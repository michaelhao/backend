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
            'details.*.grade_id' => ['nullable', 'integer', 'exists:grades,id'],
            'details.*.addon_id' => ['nullable', 'integer', 'exists:addons,id'],
            'details.*.payment_type' => ['nullable', 'integer', 'in:1,2,3'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'integer'],
            'details.*.total_price' => ['required', 'integer'],
            'details.*.name' => ['required', 'string', 'max:100'],
            'details.*.start_at' => ['required', 'date', 'after_or_equal:today'],
            'details.*.expired_at' => ['required', 'date', 'after:details.*.start_at'],
            'details.*.total_months' => ['required', 'integer', 'min:0', 'max:36'],
            'details.*.memo' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'integer', 'in:1,2,3'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_name' => ['nullable', 'string', 'max:100', 'required_with:discount_amount'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $discountAmount = (int) ($this->input('discount_amount') ?? 0);

            if ($discountAmount > 0) {
                $subtotal = 0;
                foreach ($this->input('details', []) as $detail) {
                    $subtotal += (int) ($detail['total_price'] ?? 0);
                }

                if ($discountAmount > $subtotal) {
                    $validator->errors()->add('discount_amount', '折抵金額不得大於小計');
                }
            }
        });
    }
}
