@extends('layouts.admin')

@section('page-title', '編輯角色')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">編輯角色：{{ $role->name }}</h2>
    </div>

    <form method="POST" action="{{ route('roles.update', $role) }}" class="bg-white rounded-lg shadow p-6 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">角色名稱</label>
            <input type="text" name="name" id="name" value="{{ old('name', $role->name) }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700 mb-1">說明</label>
            <input type="text" name="description" id="description" value="{{ old('description', $role->description) }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">預設頁面</label>
            <x-searchable-select name="default_route" :value="old('default_route', $role->default_route)" :permissions="$permissions" />
            @error('default_route')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-3">權限</label>
            @error('permissions')
                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <div class="space-y-4">
                @foreach ($permissions as $module => $modulePermissions)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <h4 class="font-medium text-gray-800 mb-2">{{ $modulePermissions->first()->description ? explode(' - ', $modulePermissions->first()->description)[0] : $module }}</h4>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($modulePermissions as $permission)
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                           {{ in_array($permission->id, old('permissions', $rolePermissionIds)) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    {{ $permission->description ? explode(' - ', $permission->description)[1] ?? $permission->action : $permission->action }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                更新
            </button>
            <a href="{{ route('roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
        </div>
    </form>
@endsection
