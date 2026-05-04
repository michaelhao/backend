<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function attempt(array $credentials): bool
    {
        return Auth::attempt($credentials);
    }

    public function loginSession(): void
    {
        request()->session()->regenerate();

        Auth::user()->loadPermissionsToSession();
    }

    public function logout(): void
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
