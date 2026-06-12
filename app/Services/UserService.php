<?php

namespace App\Services;

use App\Exceptions\UserOperationException;
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

    public function findUserById(int $id): ?User
    {
        return $this->userRepository->getById($id);
    }

    public function createUser(array $data): User
    {
        return $this->userRepository->create($data);
    }

    /**
     * @throws UserOperationException 修改自己的角色時拋出
     */
    public function updateUser(User $user, array $data, int $actingUserId): void
    {
        if ($user->id === $actingUserId && isset($data['role_id']) && (int) $data['role_id'] !== $user->role_id) {
            throw new UserOperationException('無法修改自己的角色');
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $this->userRepository->update($user, $data);
    }

    /**
     * @throws UserOperationException 刪除自己的帳號時拋出
     */
    public function deleteUser(User $user, int $actingUserId): void
    {
        if ($user->id === $actingUserId) {
            throw new UserOperationException('無法刪除自己的帳號');
        }

        $this->userRepository->delete($user);
    }
}
