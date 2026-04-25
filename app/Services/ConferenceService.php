<?php

namespace App\Services;

use App\Enums\ConferenceStatus;
use App\Models\Conference;
use App\Repositories\ConferenceRepository;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConferenceService
{
    public function __construct(private ConferenceRepository $conferenceRepository) {}

    /**
     * @return array{conferences: LengthAwarePaginator, filters: array, perPage: int}
     */
    public function getIndexData(Request $request): array
    {
        $perPage = in_array((int) $request->per_page, [50, 100, 150, 200])
            ? (int) $request->per_page
            : 50;

        $filters = $request->only(['keyword', 'status']);

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

    public function createConference(array $data): Conference
    {
        return DB::transaction(fn () => $this->conferenceRepository->create($data));
    }

    public function updateConference(Conference $conference, array $data): void
    {
        DB::transaction(fn () => $this->conferenceRepository->update($conference, $data));
    }
}
