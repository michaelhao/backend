<?php

namespace App\Http\Requests;

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
            'grade_id' => ['required', 'integer', Rule::exists('grades', 'id')],
            'status' => ['required', new Enum(ShopStatus::class)],
            'admin.name' => ['required', 'string', 'max:20'],
            'admin.email' => ['required', 'email'],
            'admin.business_number' => ['nullable', 'string', 'regex:/^\d{8}$/'],
            'admin.company_name' => ['nullable', 'string'],
        ];
    }
}
