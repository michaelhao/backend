@extends('layouts.admin')

@section('page-title', '使用者管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="page-title">使用者管理</h2>
        <x-permission name="User.create">
            <a href="{{ route('users.create') }}"
               class="btn-primary">
                新增使用者
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

<div id="row-delete"></div>

@push('scripts')
    @vite('resources/js/users/index.js')
@endpush
