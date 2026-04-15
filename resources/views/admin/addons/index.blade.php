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

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 transition-opacity duration-500 flash-message">{{ session('error') }}</div>
    @endif

    {{-- 搜尋區塊 --}}
    <form method="GET" action="{{ route('addons.index') }}" class="mb-6 bg-white rounded-lg shadow p-4">
        <div class="flex items-end gap-4">
            <div class="flex-1">
                <label for="keyword" class="block text-xs font-medium text-gray-600 mb-1">關鍵字（功能名稱）</label>
                <input id="keyword" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                       placeholder="搜尋功能名稱"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="per_page" class="block text-xs font-medium text-gray-600 mb-1">每頁</label>
                <select id="per_page" name="per_page"
                        class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @foreach ([50, 100, 150, 200] as $option)
                        <option value="{{ $option }}" {{ $perPage == $option ? 'selected' : '' }}>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="bg-gray-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-700 transition-colors">
                搜尋
            </button>
        </div>
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
                                <form method="POST" action="{{ route('addons.destroy', $addon) }}" class="inline"
                                      onsubmit="return confirm('確定要刪除此附加功能嗎？')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800">刪除</button>
                                </form>
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

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.flash-message').forEach(el => {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        });
    });
</script>
@endpush
