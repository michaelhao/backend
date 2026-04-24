<?php

namespace App\Http\Requests;

use App\Models\BillDetail;
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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $billId = (int) $this->route('id');
            $ids = $this->input('detail_ids', []);
            $matched = BillDetail::whereIn('id', $ids)
                ->where('bill_id', $billId)
                ->count();
            if ($matched !== count($ids)) {
                $validator->errors()->add('detail_ids', '部分明細不屬於此帳單');
            }
        });
    }
}
