<?php

namespace App\Http\Requests;

use App\Services\PermissionRouteResolver;
use Illuminate\Contracts\Validation\ValidationRule;
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

    private function defaultRouteResolvableRule(): ValidationRule
    {
        $resolver = app(PermissionRouteResolver::class);

        return new class($resolver) implements ValidationRule
        {
            public function __construct(private PermissionRouteResolver $resolver) {}

            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if (! is_string($value) || $value === '') {
                    return;
                }

                if ($this->resolver->routeNameFor($value) === null) {
                    $fail('所選的預設頁面尚未對應到任何路由。');
                }
            }
        };
    }
}
