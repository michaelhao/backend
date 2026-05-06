<?php

namespace App\Http\Requests;

use App\Enums\GradeStatus;
use App\Enums\ShopStatus;
use App\Models\Shop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class ShopUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $shop = Shop::find($this->route('id'));

        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', Rule::unique('shops', 'email')->ignore($shop->id)],
            'grade_id' => ['required', 'integer', $this->gradeIdExistsRule($shop)],
            'status' => ['required', new Enum(ShopStatus::class)],
            'admin.name' => ['required', 'string', 'max:20'],
            'admin.email' => ['required', 'email'],
            'admin.business_number' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'admin.company_name' => ['nullable', 'string'],
        ];
    }

    /**
     * 變更 grade 必須指向 Active 版本;若維持原 grade(即使該版本已停用),允許不變更。
     */
    private function gradeIdExistsRule(?Shop $shop): \Illuminate\Validation\Rules\Exists
    {
        if ($shop && (int) $this->input('grade_id') === $shop->grade_id) {
            return Rule::exists('grades', 'id');
        }

        return Rule::exists('grades', 'id')->where('status', GradeStatus::Active->value);
    }
}
