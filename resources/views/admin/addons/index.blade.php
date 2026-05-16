@extends('layouts.admin')

@section('page-title', '附加功能管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">附加功能管理</h2>
        <x-permission name="Addon.create">
            <a href="{{ route('addons.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增附加功能
            </a>
        </x-permission>
    </div>

    <div class="flash-area">
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
        @endif
    </div>

    {{-- 搜尋區塊 --}}
    <form method="GET" action="{{ route('addons.index') }}" class="mb-6 bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="keyword" class="block text-xs font-medium text-gray-600 mb-1">關鍵字（名稱）</label>
                <x-form-input name="keyword" :value="$filters['keyword'] ?? ''" placeholder="搜尋功能名稱" class="w-full" />
            </div>
            <div>
                <label for="type" class="block text-xs font-medium text-gray-600 mb-1">類型</label>
                <x-form-select name="type" class="w-full">
                    <option value="">全部</option>
                    <option value="1" {{ ($filters['type'] ?? '') === '1' ? 'selected' : '' }}>功能</option>
                    <option value="2" {{ ($filters['type'] ?? '') === '2' ? 'selected' : '' }}>配額</option>
                </x-form-select>
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-600 mb-1">狀態</label>
                <x-form-select name="status" class="w-full">
                    <option value="">全部</option>
                    <option value="1" {{ ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>上架</option>
                    <option value="0" {{ ($filters['status'] ?? '') === '0' ? 'selected' : '' }}>下架</option>
                </x-form-select>
            </div>
            <div>
                <label for="grade_id" class="block text-xs font-medium text-gray-600 mb-1">所屬版本</label>
                <x-form-select name="grade_id" class="w-full">
                    <option value="">全部</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" {{ ($filters['grade_id'] ?? '') == $grade->id ? 'selected' : '' }}>
                            {{ $grade->name }}
                        </option>
                    @endforeach
                </x-form-select>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                搜尋
            </button>
            <a href="{{ route('addons.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">清除</a>

            {{-- Page size --}}
            <div class="ml-auto flex items-center gap-2">
                <label class="text-xs text-gray-600">每頁顯示</label>
                <select id="per-page-select" name="per_page" form="per-page-form"
                        class="rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none">
                    @foreach ([50, 100, 150, 200] as $size)
                        <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-gray-600">筆</span>
            </div>
        </div>
    </form>

    {{-- Hidden per-page form (submits per_page + existing filters) --}}
    <form id="per-page-form" method="GET" action="{{ route('addons.index') }}">
        @foreach ($filters as $key => $value)
            @if ($value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">圖片</th>
                    <th class="px-6 py-3">名稱</th>
                    <th class="px-6 py-3">類型</th>
                    <th class="px-6 py-3">價格</th>
                    <th class="px-6 py-3">單位</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">同步</th>
                    <th class="px-6 py-3">所屬版本</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($addons as $addon)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            @if ($addon->image)
                                <img src="{{ asset('storage/' . $addon->image->image_url) }}"
                                     alt="{{ $addon->name }}"
                                     class="h-10 w-10 object-cover rounded">
                            @else
                                <div class="h-10 w-10 bg-gray-100 rounded flex items-center justify-center text-gray-400 text-xs">無圖</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $addon->name }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $addon->type === \App\Enums\AddonType::Feature ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                {{ $addon->type === \App\Enums\AddonType::Feature ? '功能' : '配額' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ number_format($addon->price) }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $addon->unit ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $addon->status === \App\Enums\AddonStatus::Active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $addon->status === \App\Enums\AddonStatus::Active ? '上架' : '下架' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if ($addon->syncing === \App\Enums\AddonSyncing::Syncing)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    同步中...
                                </span>
                            @else
                                <span class="text-gray-400 text-xs">已同步</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ $addon->grades->pluck('name')->join(', ') ?: '-' }}
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="Addon.update">
                                <a href="{{ route('addons.edit', $addon) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                            <x-permission name="Addon.delete">
                                <button type="button"
                                        class="delete-btn text-red-600 hover:text-red-800"
                                        data-url="{{ route('addons.destroy', $addon) }}"
                                        data-name="{{ $addon->name }}">
                                    刪除
                                </button>
                            </x-permission>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-6 py-8 text-center text-gray-400">目前沒有附加功能</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $addons->withQueryString()->links() }}
    </div>
@endsection

<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">確認刪除</h3>
        <p class="text-sm text-gray-600 mb-6">
            確定要刪除「<span id="delete-modal-name" class="font-medium text-gray-900"></span>」嗎？此操作無法復原。
        </p>
        <div class="flex justify-end gap-3">
            <button id="delete-modal-cancel"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                取消
            </button>
            <button id="delete-modal-confirm"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                確認刪除
            </button>
        </div>
    </div>
</div>

@push('scripts')
    @vite('resources/js/addons/index.js')
@endpush
