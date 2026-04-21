<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WriteoffBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'detail_ids' => ['required', 'array', 'min:1'],
            'detail_ids.*' => ['required', 'integer', 'exists:bills_details,id'],
        ];
    }
}
