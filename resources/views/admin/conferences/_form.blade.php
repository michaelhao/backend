<form method="POST" action="{{ $action }}" class="bg-white rounded-lg shadow p-6 space-y-6">
    @csrf
    @if ($method === 'PUT')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">說明會名稱</label>
            <input type="text" name="name" id="name" value="{{ old('name', $conference->name ?? '') }}" maxlength="100"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('name')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
            <select name="status" id="status"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}"
                        {{ old('status', $conference?->status->value ?? \App\Enums\ConferenceStatus::Active->value) == $status->value ? 'selected' : '' }}>
                        {{ $status === \App\Enums\ConferenceStatus::Active ? '啟用' : '停用' }}
                    </option>
                @endforeach
            </select>
            @error('status')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">報名期間</h3>
        </div>

        <div>
            <label for="register_started_at" class="block text-sm font-medium text-gray-700 mb-1">報名開始</label>
            <input type="datetime-local" name="register_started_at" id="register_started_at"
                   value="{{ old('register_started_at', isset($conference) ? $conference->register_started_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('register_started_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="register_ended_at" class="block text-sm font-medium text-gray-700 mb-1">報名截止</label>
            <input type="datetime-local" name="register_ended_at" id="register_ended_at"
                   value="{{ old('register_ended_at', isset($conference) ? $conference->register_ended_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('register_ended_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="sm:col-span-2 border-t border-gray-200 pt-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">活動期間</h3>
        </div>

        <div>
            <label for="started_at" class="block text-sm font-medium text-gray-700 mb-1">活動開始</label>
            <input type="datetime-local" name="started_at" id="started_at"
                   value="{{ old('started_at', isset($conference) ? $conference->started_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('started_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ended_at" class="block text-sm font-medium text-gray-700 mb-1">活動結束</label>
            <input type="datetime-local" name="ended_at" id="ended_at"
                   value="{{ old('ended_at', isset($conference) ? $conference->ended_at->format('Y-m-d\TH:i') : '') }}"
                   class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
            @error('ended_at')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-center gap-4">
        <button type="submit"
                class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
            {{ $submitLabel }}
        </button>
        <a href="{{ route('conferences.index') }}" class="text-sm text-gray-500 hover:text-gray-700">取消</a>
    </div>
</form>
