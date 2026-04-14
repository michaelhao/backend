@extends('layouts.admin')

@section('page-title', '商店管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">商店管理</h2>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 transition-opacity duration-500 flash-message">{{ session('success') }}</div>
    @endif

    {{-- 搜尋區塊 --}}
    <form method="GET" action="{{ route('shops.index') }}" class="mb-6 bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="keyword" class="block text-xs font-medium text-gray-600 mb-1">關鍵字（商店名稱）</label>
                <input id="keyword" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                       placeholder="搜尋商店名稱"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="grade_id" class="block text-xs font-medium text-gray-600 mb-1">版本</label>
                <select id="grade_id" name="grade_id"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">全部</option>
                    @foreach ($grades as $grade)
                        <option value="{{ $grade->id }}" {{ ($filters['grade_id'] ?? '') == $grade->id ? 'selected' : '' }}>
                            {{ $grade->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="business_number" class="block text-xs font-medium text-gray-600 mb-1">統一編號</label>
                <input id="business_number" type="text" name="business_number" value="{{ $filters['business_number'] ?? '' }}"
                       placeholder="精準搜尋統一編號"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="is_certified" class="block text-xs font-medium text-gray-600 mb-1">認證狀態</label>
                <select id="is_certified" name="is_certified"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">全部</option>
                    <option value="1" {{ ($filters['is_certified'] ?? '') === '1' ? 'selected' : '' }}>已認證</option>
                    <option value="0" {{ ($filters['is_certified'] ?? '') === '0' ? 'selected' : '' }}>未認證</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                搜尋
            </button>
            <a href="{{ route('shops.index') }}"
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
    <form id="per-page-form" method="GET" action="{{ route('shops.index') }}">
        @foreach ($filters as $key => $value)
            @if ($value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
    </form>

    {{-- 資料表格 --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">商店名稱</th>
                    <th class="px-6 py-3">版本</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">認證狀態</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($shops as $shop)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $shop->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $shop->grade?->name ?? '-' }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusLabels = [
                                    \App\Enums\ShopStatus::Active->value   => ['label' => '啟用',  'class' => 'bg-green-100 text-green-800'],
                                    \App\Enums\ShopStatus::Closed->value   => ['label' => '關閉',  'class' => 'bg-gray-100 text-gray-600'],
                                    \App\Enums\ShopStatus::Expired->value  => ['label' => '過期',  'class' => 'bg-yellow-100 text-yellow-800'],
                                    \App\Enums\ShopStatus::Archived->value => ['label' => '封存',  'class' => 'bg-red-100 text-red-800'],
                                ];
                                $statusInfo = $statusLabels[$shop->status->value] ?? ['label' => '未知', 'class' => 'bg-gray-100 text-gray-600'];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusInfo['class'] }}">
                                {{ $statusInfo['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if ($shop->admin?->business_number)
                                <button type="button"
                                        class="cert-badge inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 cursor-pointer hover:bg-blue-200 transition-colors"
                                        data-business-number="{{ $shop->admin->business_number }}"
                                        data-company-name="{{ $shop->admin->company_name }}">
                                    已認證
                                </button>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-400">
                                    未認證
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <x-permission name="Shop.update">
                                <a href="{{ route('shops.edit', $shop) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400 text-sm">沒有符合條件的商店</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 分頁 --}}
    @if ($shops->hasPages())
        <div class="mt-4">
            {{ $shops->appends(request()->query())->links() }}
        </div>
    @endif

    {{-- 認證詳情 Modal --}}
    <div id="cert-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">認證資訊</h3>
            <div class="space-y-3 text-sm">
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 flex-shrink-0">統一編號：</span>
                    <span id="modal-business-number" class="text-gray-800 font-mono"></span>
                </div>
                <div class="flex gap-2">
                    <span class="text-gray-500 w-24 flex-shrink-0">公司名稱：</span>
                    <span id="modal-company-name" class="text-gray-800"></span>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                <button id="cert-modal-close"
                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 transition-colors">
                    關閉
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Flash message auto-dismiss
        document.querySelectorAll('.flash-message').forEach(el => {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 5000);
        });

        // Per-page select submit
        document.getElementById('per-page-select').addEventListener('change', function () {
            document.getElementById('per-page-form').submit();
        });

        // Cert badge modal
        const modal = document.getElementById('cert-modal');
        const modalBusinessNumber = document.getElementById('modal-business-number');
        const modalCompanyName = document.getElementById('modal-company-name');

        document.querySelectorAll('.cert-badge').forEach(badge => {
            badge.addEventListener('click', function () {
                modalBusinessNumber.textContent = this.dataset.businessNumber;
                modalCompanyName.textContent = this.dataset.companyName || '-';
                modal.classList.remove('hidden');
            });
        });

        document.getElementById('cert-modal-close').addEventListener('click', () => {
            modal.classList.add('hidden');
        });

        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.classList.add('hidden');
        });
    });
</script>
@endpush
