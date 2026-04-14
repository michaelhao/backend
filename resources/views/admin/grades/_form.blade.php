<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">代碼</label>
        <input type="text" name="code" id="code" value="{{ old('code', $grade->code ?? '') }}"
               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">名稱</label>
        <input type="text" name="name" id="name" value="{{ old('name', $grade->name ?? '') }}"
               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">價格</label>
        <input type="number" name="price" id="price" value="{{ old('price', $grade->price ?? '') }}" min="2"
               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
        <select name="status" id="status"
                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            <option value="1" {{ old('status', $grade?->status->value ?? 1) == 1 ? 'selected' : '' }}>啟用</option>
            <option value="0" {{ old('status', $grade?->status->value ?? 1) == 0 ? 'selected' : '' }}>關閉</option>
        </select>
        @error('status')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('grades.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>
