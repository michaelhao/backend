@extends('layouts.admin')

@section('page-title', '角色管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">角色管理</h2>
        <x-permission name="Role.create">
            <a href="{{ route('roles.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增角色
            </a>
        </x-permission>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
    @endif

    <script>
        document.querySelectorAll('.flash-message').forEach(el => {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        });
    </script>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">角色名稱</th>
                    <th class="px-6 py-3">說明</th>
                    <th class="px-6 py-3">預設頁面</th>
                    <th class="px-6 py-3">權限數</th>
                    <th class="px-6 py-3">使用者數</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($roles as $role)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $role->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->description }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $permissionDescriptions[$role->default_route] ?? $role->default_route }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->permissions_count }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $role->users_count }}</td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="Role.update">
                                <a href="{{ route('roles.edit', $role) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                            <x-permission name="Role.delete">
                                <form method="POST" action="{{ route('roles.delete', $role) }}" class="inline"
                                      onsubmit="return confirm('確定要刪除此角色嗎？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">刪除</button>
                                </form>
                            </x-permission>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
