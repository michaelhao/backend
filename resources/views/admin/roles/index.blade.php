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

<div id="row-delete"></div>

@push('scripts')
    @vite('resources/js/roles/index.js')
@endpush
