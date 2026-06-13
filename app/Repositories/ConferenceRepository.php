<?php

namespace App\Repositories;

use App\Models\Conference;
use Illuminate\Pagination\LengthAwarePaginator;

class ConferenceRepository
{
    public function paginate(int $perPage, array $filters): LengthAwarePaginator
    {
        return Conference::query()
            ->when($filters['keyword'] ?? null, function ($query, $keyword) {
                $query->where('name', 'like', "%{$keyword}%");
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
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
