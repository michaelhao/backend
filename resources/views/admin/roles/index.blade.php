@extends('layouts.admin')

@section('page-title', '角色管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="page-title">角色管理</h2>
        <x-permission name="Role.create">
            <a href="{{ route('roles.create') }}"
               class="btn-primary">
                新增角色
            </a>
        </x-permission>
    </div>

    <div class="flash-area">
        @if (session('success'))
            <div class="flash flash-success flash-message">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="flash flash-error flash-message">{{ session('error') }}</div>
        @endif
    </div>

    <div class="card">
        <table class="table">
            <thead class="table-head">
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
                                <button type="button"
                                        class="delete-btn text-red-600 hover:text-red-800"
                                        data-url="{{ route('roles.destroy', $role) }}"
                                        data-name="{{ $role->name }}">
                                    刪除
                                </button>
                            </x-permission>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection

<div id="delete-modal" class="modal-overlay hidden">
    <div class="modal-panel">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">確認刪除</h3>
        <p class="text-sm text-gray-600 mb-6">
            確定要刪除「<span id="delete-modal-name" class="font-medium text-gray-900"></span>」嗎？此操作無法復原。
        </p>
        <div class="modal-actions">
            <button id="delete-modal-cancel"
                    class="btn-cancel">
                取消
            </button>
            <button id="delete-modal-confirm"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                確認刪除
            </button>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/roles/index.js')
@endpush
