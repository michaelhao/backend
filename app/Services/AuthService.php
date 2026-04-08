<?php

namespace App\Services;

use App\Repositories\RoleRepository;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(private RoleRepository $roleRepository) {}

    public function attempt(array $credentials, bool $remember): bool
    {
        return Auth::attempt($credentials, $remember);
    }

    public function loginSession(): void
    {
        request()->session()->regenerate();

        $user = Auth::user();
        $permissions = $user->role
            ? $this->roleRepository->getPermissionNames($user->role)
            : [];

        session(['permissions' => $permissions]);
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
