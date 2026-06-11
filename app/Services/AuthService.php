<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function __construct(private UserRepository $userRepository) {}

    public function attempt(array $credentials): bool
    {
        return Auth::attempt($credentials);
    }

    public function invalidateUserSessions(int $userId): void
    {
        $this->userRepository->deleteSessionsByUserId($userId);
    }

    public function loadPermissionsToSession(): void
    {
        Auth::user()->loadPermissionsToSession();
    }

    public function logout(): void
    {
        Auth::logout();
    }
}
