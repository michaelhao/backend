<?php

namespace App\Http\Requests;

use App\Enums\BillDetailType;
use App\Enums\GradeStatus;
use App\Models\Grade;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'details.*.grade_id' => [
                'nullable', 'integer',
                Rule::exists('grades', 'id')->where('status', GradeStatus::Active->value),
                'required_if:details.*.type,1,2',
            ],
            'details.*.addon_id' => ['nullable', 'integer', 'exists:addons,id', 'required_if:details.*.type,3'],
            'details.*.payment_type' => ['nullable', 'integer', 'in:1,2,3'],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.start_at' => ['required', 'date', 'after_or_equal:today'],
            'details.*.total_months' => ['required', 'integer', 'min:0', 'max:36'],
            'details.*.memo' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['required', 'integer', 'in:1,2,3'],
            'discount_amount' => ['nullable', 'integer', 'min:0'],
            'discount_id' => ['nullable', 'integer', 'exists:bills_discount,id', 'required_with:discount_amount'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $seenAddonIds = [];
            foreach ((array) $this->input('details', []) as $i => $d) {
                if ((int) ($d['type'] ?? 0) !== BillDetailType::Addons->value || ! isset($d['addon_id'])) {
                    continue;
                }
                $addonId = (int) $d['addon_id'];
                if (isset($seenAddonIds[$addonId])) {
                    $validator->errors()->add("details.{$i}.addon_id", '同一張帳單內不可重複加購相同功能');
                } else {
                    $seenAddonIds[$addonId] = true;
                }
            }

            $shop = Shop::with('grade')->find((int) $this->input('shop_id'));
            if (! $shop || ! $shop->expired_at) {
                return;
            }
            $currentWeight = $shop->grade?->weight ?? 0;
            $minStartForNonUpgrade = $shop->expired_at->copy()->addDay()->startOfDay();

            foreach ((array) $this->input('details', []) as $i => $d) {
                $type = (int) ($d['type'] ?? 0);
                // only plain grade rows have the up/renew/down distinction;
                // upgrade_fee_diff (type=2) already implies upgrade.
                if ($type !== BillDetailType::Grades->value) {
                    continue;
                }
                $grade = Grade::find($d['grade_id'] ?? null);
                if (! $grade) {
                    continue;
                }
                if ($grade->weight > $currentWeight) {
                    continue; // upgrade: today is fine
                }
                $startAt = Carbon::parse($d['start_at']);
                if ($startAt->lt($minStartForNonUpgrade)) {
                    $validator->errors()->add(
                        "details.{$i}.start_at",
                        '續約或降級的開始日需晚於目前合約到期日'
                    );
                }
            }
        });
    }
}
