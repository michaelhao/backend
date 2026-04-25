@extends('layouts.admin')

@section('page-title', '說明會管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">說明會管理</h2>
        <x-permission name="Conference.create">
            <a href="{{ route('conferences.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                新增說明會
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
    <form method="GET" action="{{ route('conferences.index') }}" class="mb-6 bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="keyword" class="block text-xs font-medium text-gray-600 mb-1">關鍵字（名稱）</label>
                <input id="keyword" type="text" name="keyword" value="{{ $filters['keyword'] ?? '' }}"
                       placeholder="搜尋說明會名稱"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
            </div>
            <div>
                <label for="status" class="block text-xs font-medium text-gray-600 mb-1">狀態</label>
                <select id="status" name="status"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    <option value="">全部</option>
                    <option value="1" {{ ($filters['status'] ?? '') === '1' ? 'selected' : '' }}>啟用</option>
                    <option value="0" {{ ($filters['status'] ?? '') === '0' ? 'selected' : '' }}>停用</option>
                </select>
            </div>
        </div>
        <div class="mt-4 flex items-center gap-3">
            <button type="submit"
                    class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                搜尋
            </button>
            <a href="{{ route('conferences.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">清除</a>

            <div class="ml-auto flex items-center gap-2">
                <label class="text-xs text-gray-600">每頁顯示</label>
                <select id="per-page-select" name="per_page" form="per-page-form"
                        class="rounded-md border border-gray-300 px-2 py-1 text-sm focus:border-blue-500 focus:outline-none"
                        onchange="document.getElementById('per-page-form').submit();">
                    @foreach ([50, 100, 150, 200] as $size)
                        <option value="{{ $size }}" {{ $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                </select>
                <span class="text-xs text-gray-600">筆</span>
            </div>
        </div>
    </form>

    <form id="per-page-form" method="GET" action="{{ route('conferences.index') }}">
        @foreach ($filters as $key => $value)
            @if ($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">名稱</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">報名期間</th>
                    <th class="px-6 py-3">活動期間</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($conferences as $conference)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $conference->name }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $conference->status === \App\Enums\ConferenceStatus::Active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $conference->status === \App\Enums\ConferenceStatus::Active ? '啟用' : '停用' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ $conference->register_started_at->format('Y-m-d H:i') }}
                            ~
                            {{ $conference->register_ended_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">
                            {{ $conference->started_at->format('Y-m-d H:i') }}
                            ~
                            {{ $conference->ended_at->format('Y-m-d H:i') }}
                        </td>
                        <td class="px-6 py-4 space-x-2">
                            <x-permission name="Conference.update">
                                <a href="{{ route('conferences.edit', $conference) }}"
                                   class="text-blue-600 hover:text-blue-800">編輯</a>
                            </x-permission>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-8 text-center text-gray-400">目前沒有說明會</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $conferences->withQueryString()->links() }}
    </div>
@endsection
