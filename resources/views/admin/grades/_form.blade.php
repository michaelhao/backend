<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div>
        <label for="code" class="form-label">代碼</label>
        <x-form-input name="code" :value="old('code', $grade->code ?? '')" class="w-full" />
        @error('code')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="form-label">名稱</label>
        <x-form-input name="name" :value="old('name', $grade->name ?? '')" class="w-full" />
        @error('name')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="form-label">價格</label>
        <x-form-input type="number" name="price" :value="old('price', $grade->price ?? '')" min="2" class="w-full" />
        @error('price')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <p class="text-xs font-semibold text-gray-500 mb-2">grades weight</p>
        <div id="grade-weight-field" data-props="@json(['excludeId' => $grade->id ?? null, 'grades' => $grades->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'weight' => $g->weight])->values(), 'checkUrl' => route('grades.check-weight'), 'currentWeight' => old('weight', $grade->weight ?? null)])"></div>
        @error('weight')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="status" class="form-label">狀態</label>
        <x-form-select name="status" class="w-full">
            <option value="1" {{ old('status', $grade?->status->value ?? 1) == 1 ? 'selected' : '' }}>啟用</option>
            <option value="0" {{ old('status', $grade?->status->value ?? 1) == 0 ? 'selected' : '' }}>關閉</option>
        </x-form-select>
        @error('status')
            <p class="form-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <button type="submit"
                class="btn-primary px-6">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('grades.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>
