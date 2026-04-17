@extends('layouts.admin')

@section('page-title', '使用者管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">使用者管理</h2>
        <x-permission name="User.create">
            <a href="{{ route('users.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增使用者
            </a>
        </x-permission>
    </div>

    <div class="flash-area">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">名稱</th>
                    <th class="px-6 py-3">電子郵件</th>
                    <th class="px-6 py-3">角色</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($users as $user)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $user->role?->name ?? '—' }}</td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="User.update">
                                <a href="{{ route('users.edit', $user) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                            <x-permission name="User.delete">
                                <button type="button"
                                        class="delete-btn text-red-600 hover:text-red-800"
                                        data-url="{{ route('users.destroy', $user) }}"
                                        data-name="{{ $user->name }}">
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

<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">確認刪除</h3>
        <p class="text-sm text-gray-600 mb-6">
            確定要刪除「<span id="delete-modal-name" class="font-medium text-gray-900"></span>」嗎？此操作無法復原。
        </p>
        <div class="flex justify-end gap-3">
            <button id="delete-modal-cancel"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
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
    @vite('resources/js/users/index.js')
@endpush
