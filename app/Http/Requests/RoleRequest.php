<?php

namespace App\Http\Requests;

use App\Services\PermissionRouteResolver;
use Closure;
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
            'default_route' => [
                'required',
                'string',
                'exists:permissions,name',
                $this->defaultRouteResolvableRule(),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'integer', 'exists:permissions,id'],
        ];
    }

    /**
     * 確認 default_route 對應的 permission 能解析到實際命名路由
     * （permission 存在於 DB ≠ controller method 仍存在）。
     */
    private function defaultRouteResolvableRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (app(PermissionRouteResolver::class)->routeNameFor($value) === null) {
                $fail('所選的預設頁面尚未對應到任何路由。');
            }
        };
    }
}
