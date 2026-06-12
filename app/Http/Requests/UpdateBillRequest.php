<?php

namespace App\Http\Requests;

use App\Enums\BillPaymentStatus;
use App\Models\Bill;
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
            $bill = Bill::find((int) $this->route('id'));
            if (! $bill) {
                return;
            }

            $currentStatus = $bill->payment_status;
            $newStatusEnum = BillPaymentStatus::tryFrom((int) $newStatus);

            if ($currentStatus === BillPaymentStatus::Paid
                && $newStatusEnum !== BillPaymentStatus::Paid) {
                $validator->errors()->add('payment_status', '已付款的帳單無法變更狀態');

                return;
            }

            if ($currentStatus === BillPaymentStatus::Invalid
                && $newStatusEnum !== BillPaymentStatus::Invalid) {
                $validator->errors()->add('payment_status', '已失效的帳單無法變更狀態');

                return;
            }

            if ($newStatusEnum === BillPaymentStatus::Paid
                && ! in_array($currentStatus, [
                    BillPaymentStatus::Pending,
                    BillPaymentStatus::Unpaid,
                    BillPaymentStatus::Paid,
                ], true)) {
                $validator->errors()->add('payment_status', '只能從待審核或待付款狀態進行付款');
            }
        });
    }
}
