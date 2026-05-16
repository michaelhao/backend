<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="code" class="block text-sm font-medium text-gray-700 mb-1">代碼</label>
        <x-form-input name="code" :value="old('code', $grade->code ?? '')" class="w-full" />
        @error('code')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">名稱</label>
        <x-form-input name="name" :value="old('name', $grade->name ?? '')" class="w-full" />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="block text-sm font-medium text-gray-700 mb-1">價格</label>
        <x-form-input type="number" name="price" :value="old('price', $grade->price ?? '')" min="2" class="w-full" />
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <p class="text-xs font-semibold text-gray-500 mb-2">grades weight</p>
        <div id="weight-list" class="text-sm text-gray-700 space-y-1 border rounded-lg p-3 bg-gray-50">
            @foreach ($grades as $g)
                <div class="flex justify-between weight-row" data-id="{{ $g->id }}">
                    <span>{{ $g->name }}</span>
                    <span>{{ $g->weight }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <label for="weight" class="block text-sm font-medium text-gray-700 mb-1">版本權重</label>
        <x-form-input type="number" name="weight"
                      :value="old('weight', $grade->weight ?? '')"
                      data-exclude-id="{{ $grade->id ?? '' }}"
                      class="w-full" />
        <div id="weight-error" class="mt-1 text-sm text-red-600 hidden"></div>
        @error('weight')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
        <x-form-select name="status" class="w-full">
            <option value="1" {{ old('status', $grade?->status->value ?? 1) == 1 ? 'selected' : '' }}>啟用</option>
            <option value="0" {{ old('status', $grade?->status->value ?? 1) == 0 ? 'selected' : '' }}>關閉</option>
        </x-form-select>
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
