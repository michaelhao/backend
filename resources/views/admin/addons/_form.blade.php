<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">功能名稱</label>
            <x-form-input name="name" :value="old('name', $addon->name ?? '')" class="w-full" />
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">類型</label>
            <x-form-select name="type" class="w-full">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}"
                        {{ old('type', $addon?->type->value ?? '') == $type->value ? 'selected' : '' }}>
                        {{ $type === \App\Enums\AddonType::Feature ? '功能' : '配額' }}
                    </option>
                @endforeach
            </x-form-select>
            @error('type')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="price" class="block text-sm font-medium text-gray-700 mb-1">售價</label>
            <x-form-input type="number" name="price" :value="old('price', $addon->price ?? '')" min="0" class="w-full" />
            @error('price')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="unit" class="block text-sm font-medium text-gray-700 mb-1">單位 <span class="text-gray-400 text-xs">（選填）</span></label>
            <x-form-input name="unit" :value="old('unit', $addon->unit ?? '')" class="w-full" />
            @error('unit')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
            <x-form-select name="status" class="w-full">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}"
                        {{ old('status', $addon?->status->value ?? \App\Enums\AddonStatus::Active->value) == $status->value ? 'selected' : '' }}>
                        {{ $status === \App\Enums\AddonStatus::Active ? '上架' : '下架' }}
                    </option>
                @endforeach
            </x-form-select>
            @error('status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">所屬版本 <span class="text-gray-400 text-xs">（可複選）</span></label>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($grades as $grade)
                @php($isLinked = in_array($grade->id, $selectedGradeIds ?? []))
                @php($isDisabled = ! $isLinked && $grade->status !== \App\Enums\GradeStatus::Active)
                <label class="flex items-center gap-2 text-sm {{ $isDisabled ? 'text-gray-400 cursor-not-allowed' : 'text-gray-700 cursor-pointer' }}">
                    <input type="checkbox" name="grade_ids[]" value="{{ $grade->id }}"
                           {{ in_array($grade->id, old('grade_ids', $selectedGradeIds ?? [])) ? 'checked' : '' }}
                           {{ $isDisabled ? 'disabled' : '' }}
                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 disabled:opacity-50">
                    {{ $grade->name }}{{ $isDisabled ? '（已停用）' : '' }}
                </label>
            @endforeach
        </div>
        @error('grade_ids')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">功能圖片 <span class="text-gray-400 text-xs">（jpg / png，最大 5MB）</span></label>

        {{-- 預覽區域 400×400 --}}
        <div id="image-preview-wrap"
             class="relative w-[200px] h-[200px] rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 overflow-hidden flex items-center justify-center cursor-pointer hover:border-blue-400 transition-colors"
             onclick="document.getElementById('image').click()">

            @if (isset($addon) && $addon?->image)
                <img id="image-preview"
                     src="{{ asset('storage/' . $addon->image->image_url) }}"
                     alt="{{ $addon->name }}"
                     class="w-full h-full object-cover">
            @else
                <img id="image-preview" src="" alt="" class="w-full h-full object-cover hidden">
            @endif

            <div id="image-placeholder" class="flex flex-col items-center gap-2 text-gray-400 pointer-events-none
                     {{ isset($addon) && $addon?->image ? 'hidden' : '' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5V19a1.5 1.5 0 001.5 1.5h15A1.5 1.5 0 0021 19v-2.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5V18"/>
                </svg>
                <span class="text-sm">點擊上傳圖片</span>
            </div>

            {{-- 有圖片時的覆蓋提示 --}}
            <div id="image-overlay"
                 class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity pointer-events-none
                        {{ isset($addon) && $addon?->image ? '' : 'hidden' }}">
                <span class="text-white text-sm font-medium">點擊更換圖片</span>
            </div>
        </div>

        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png" class="hidden">
        <input type="hidden" name="remove_image" id="remove_image" value="{{ old('remove_image', '0') }}">

        <div class="mt-3 flex items-center gap-2">
            <button type="button"
                    onclick="document.getElementById('image').click()"
                    class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                選擇圖片
            </button>

            <button type="button"
                    id="image-remove-btn"
                    class="inline-flex items-center gap-2 border border-red-500 text-red-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-50 transition-colors {{ isset($addon) && $addon?->image ? '' : 'hidden' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                刪除圖片
            </button>

            <span id="image-filename" class="text-sm text-gray-500"></span>
        </div>

        @error('image')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-4">
        <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('addons.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>

@push('scripts')
    @vite('resources/js/addons/form.js')
@endpush
