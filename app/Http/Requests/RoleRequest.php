<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $roleId = $this->route('id');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'default_route' => ['required', 'string', 'exists:permissions,name'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'integer', 'exists:permissions,id'],
        ];
    }
}
