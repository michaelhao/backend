<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">角色名稱</label>
        <x-form-input name="name" :value="old('name', $role->name ?? '')" class="w-full" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">說明</label>
        <x-form-input name="description" :value="old('description', $role->description ?? '')" class="w-full" />
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">預設頁面</label>
        <x-searchable-select name="default_route" :value="old('default_route', $role->default_route ?? '')" :permissions="$permissions" />
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
            {{ $submitLabel }}
        </button>
        <a href="{{ route('roles.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>
