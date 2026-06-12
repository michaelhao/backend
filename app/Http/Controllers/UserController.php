<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Exceptions\UserOperationException;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    #[RequiresPermission('User.index')]
    public function index()
    {
        $data = $this->userService->getIndexData();

        return view('admin.users.index', $data);
    }

    #[RequiresPermission('User.create')]
    public function create()
    {
        $data = $this->userService->getCreateData();

        return view('admin.users.create', $data);
    }

    #[RequiresPermission('User.create')]
    public function store(StoreUserRequest $request)
    {
        $this->userService->createUser(
            $request->safe()->only(['name', 'email', 'password', 'role_id']),
        );

        return redirect()->route('users.index')->with('success', '使用者已建立');
    }

    #[RequiresPermission('User.update')]
    public function edit(int $id)
    {
        $user = $this->userService->findUserById($id);
        if (! $user) {
            return redirect()->route('users.index')->with('error', '找不到該使用者');
        }

        $data = $this->userService->getEditData($user);

        return view('admin.users.edit', $data);
    }

    #[RequiresPermission('User.update')]
    public function update(UpdateUserRequest $request, int $id)
    {
        $user = $this->userService->findUserById($id);
        if (! $user) {
            return redirect()->route('users.index')->with('error', '找不到該使用者');
        }

        try {
            $this->userService->updateUser(
                $user,
                $request->safe()->only(['name', 'email', 'password', 'role_id']),
                auth()->id(),
            );
        } catch (UserOperationException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')->with('success', '使用者已更新');
    }

    #[RequiresPermission('User.delete')]
    public function destroy(int $id): JsonResponse
    {
        $user = $this->userService->findUserById($id);
        if (! $user) {
            return response()->json(['message' => '找不到該使用者'], 422);
        }

        try {
            $this->userService->deleteUser($user, auth()->id());
        } catch (UserOperationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => '使用者已刪除']);
    }
}
