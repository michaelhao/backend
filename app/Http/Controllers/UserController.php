<?php

namespace App\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Http\Requests\UserRequest;
use App\Models\User;
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
    public function store(UserRequest $request)
    {
        $this->userService->createUser(
            $request->safe()->only(['name', 'email', 'password', 'role_id']),
        );

        return redirect()->route('users.index')->with('success', '使用者已建立');
    }

    #[RequiresPermission('User.update')]
    public function edit(int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return redirect()->route('users.index')->with('error', '找不到該使用者');
        }

        $data = $this->userService->getEditData($user);

        return view('admin.users.edit', $data);
    }

    #[RequiresPermission('User.update')]
    public function update(UserRequest $request, int $id)
    {
        $user = User::find($id);
        if (! $user) {
            return redirect()->route('users.index')->with('error', '找不到該使用者');
        }

        $data = $request->safe()->only(['name', 'email', 'password', 'role_id']);

        if ($user->id === auth()->id() && isset($data['role_id']) && (int) $data['role_id'] !== $user->role_id) {
            return redirect()->back()->with('error', '無法修改自己的角色');
        }

        $this->userService->updateUser($user, $data);

        return redirect()->route('users.index')->with('success', '使用者已更新');
    }

    #[RequiresPermission('User.delete')]
    public function destroy(int $id): JsonResponse
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => '找不到該使用者'], 422);
        }

        if ($user->id === auth()->id()) {
            return response()->json(['message' => '無法刪除自己的帳號'], 422);
        }

        $this->userService->deleteUser($user);

        return response()->json(['message' => '使用者已刪除']);
    }
}
