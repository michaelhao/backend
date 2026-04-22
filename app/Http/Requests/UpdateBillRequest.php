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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $newStatus = $this->input('payment_status');
            if ($newStatus === null) {
                return;
            }
            $bill = \App\Models\Bill::find((int) $this->route('id'));
            if ($bill && $bill->payment_status === \App\Enums\BillPaymentStatus::Paid
                && (int) $newStatus !== \App\Enums\BillPaymentStatus::Paid->value) {
                $validator->errors()->add('payment_status', '已付款的帳單無法變更狀態');
            }
        });
    }
}
