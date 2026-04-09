<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,
    ) {}

    /**
     * @return array{users: Collection}
     */
    public function getIndexData(): array
    {
        return [
            'users' => $this->userRepository->getAllWithRole(),
        ];
    }

    /**
     * @return array{roles: Collection}
     */
    public function getCreateData(): array
    {
        return [
            'roles' => $this->roleRepository->getAll(),
        ];
    }

    /**
     * @return array{user: User, roles: Collection}
     */
    public function getEditData(User $user): array
    {
        return [
            'user' => $user->load('role'),
            'roles' => $this->roleRepository->getAll(),
        ];
    }

    public function createUser(array $data): User
    {
        return $this->userRepository->create($data);
    }

    public function updateUser(User $user, array $data): void
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->userRepository->update($user, $data);
    }

    public function deleteUser(User $user): void
    {
        $this->userRepository->delete($user);
    }
}
