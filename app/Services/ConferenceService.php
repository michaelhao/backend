<?php

namespace App\Services;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Repositories\ConferenceRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ConferenceService
{
    public function __construct(private ConferenceRepository $conferenceRepository) {}

    public function findConferenceById(int $id): ?Conference
    {
        return $this->conferenceRepository->getById($id);
    }

    /**
     * @param  array<string, string>  $filters
     * @return array{conferences: LengthAwarePaginator, filters: array, perPage: int}
     */
    public function getIndexData(array $filters, int $perPage): array
    {
        return [
            'conferences' => $this->conferenceRepository->paginate($perPage, $filters),
            'filters' => $filters,
            'perPage' => $perPage,
        ];
    }

    /**
     * @return array{statuses: ConferenceStatus[]}
     */
    public function getCreateData(): array
    {
        return [
            'statuses' => ConferenceStatus::cases(),
        ];
    }

    /**
     * @return array{conference: Conference, statuses: ConferenceStatus[]}
     */
    public function getEditData(Conference $conference): array
    {
        return [
            'conference' => $conference,
            'statuses' => ConferenceStatus::cases(),
        ];
    }

    public function createConference(array $data): void
    {
        $this->conferenceRepository->create($data);
    }

    public function updateConference(Conference $conference, array $data): void
    {
        $this->conferenceRepository->update($conference, $data);
    }
}
