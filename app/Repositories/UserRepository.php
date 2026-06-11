<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    public function getAllWithRole(): Collection
    {
        return User::with('role')->latest()->get();
    }

    public function getOrderedByName(): Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data): void
    {
        $user->update($data);
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function deleteSessionsByUserId(int $userId): void
    {
        DB::table('sessions')->where('user_id', $userId)->delete();
    }
}
