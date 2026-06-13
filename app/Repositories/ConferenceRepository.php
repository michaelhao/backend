<?php

namespace App\Repositories;

use App\Models\Conference;
use Illuminate\Pagination\LengthAwarePaginator;

class ConferenceRepository
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return Conference::query()
            ->when(filled($filters['keyword'] ?? null), fn ($q) => $q->where('name', 'like', '%'.$filters['keyword'].'%'))
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('started_at')
            ->paginate($perPage);
    }

    public function getById(int $id): ?Conference
    {
        return Conference::find($id);
    }

    public function create(array $data): Conference
    {
        return Conference::create($data);
    }

    public function update(Conference $conference, array $data): void
    {
        $conference->update($data);
    }
}
