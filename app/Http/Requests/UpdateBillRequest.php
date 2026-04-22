<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_status' => ['nullable', 'integer', 'between:1,4'],
            'paid_at'        => ['nullable', 'date'],
            'invoice_no'     => ['nullable', 'string', 'max:100'],
        ];
    }
}
