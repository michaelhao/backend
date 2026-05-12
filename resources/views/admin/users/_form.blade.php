<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">名稱</label>
        <x-form-input name="name" :value="old('name', $user->name ?? '')" class="w-full" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">電子郵件</label>
        <x-form-input type="email" name="email" :value="old('email', $user->email ?? '')" class="w-full" />
        @error('email')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1">密碼</label>
        <x-password-input name="password" placeholder="{{ $method === 'PUT' ? '留空則不修改' : '' }}" />
    </div>

    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">確認密碼</label>
        <x-password-input name="password_confirmation" />
    </div>

    <div>
        <label for="role_id" class="block text-sm font-medium text-gray-700 mb-1">角色</label>
        <x-form-select name="role_id" class="w-full">
            <option value="">請選擇角色</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" {{ old('role_id', $user->role_id ?? '') == $role->id ? 'selected' : '' }}>
                    {{ $role->name }}
                </option>
            @endforeach
        </x-form-select>
        @error('role_id')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>
