@extends('layouts.admin')

@section('page-title', '儀表板')

@section('content')
    {{-- Greeting --}}
    <div class="mb-6">
        <p class="text-sm text-gray-400 mb-1">{{ $today }}</p>
        <h2 class="page-title">嗨，歡迎回來，{{ Auth::user()->name }}！👋</h2>
        <p class="text-gray-500 mt-1">以下是您今日的重要資訊總覽</p>
    </div>

    {{-- Stat badges --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="rounded-xl p-4" style="background-color: #fff7ed;">
            <p class="text-3xl font-bold text-gray-800">{{ count($overview['new_shops']) }}</p>
            <p class="text-sm text-gray-500 mt-1">今日新增商店</p>
        </div>
        <div class="rounded-xl p-4" style="background-color: #f0fdf4;">
            <p class="text-3xl font-bold text-gray-800">{{ count($overview['today_conferences']) }}</p>
            <p class="text-sm text-gray-500 mt-1">今日說明會</p>
        </div>
        <div class="rounded-xl p-4" style="background-color: #faf5ff;">
            <p class="text-3xl font-bold text-gray-800">{{ count($overview['expiring_shops']) }}</p>
            <p class="text-sm text-gray-500 mt-1">半年內到期商店</p>
        </div>
    </div>

    {{-- Panels --}}
    <div class="space-y-4">

        {{-- 今日新增負責商店（預設展開）--}}
        <details open class="bg-white rounded-xl shadow">
            <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none list-none">
                <span class="text-orange-400">🏪</span>
                <span class="font-semibold text-gray-800">今日新增負責商店</span>
                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                    {{ count($overview['new_shops']) }} 筆
                </span>
                <svg class="ml-auto w-4 h-4 text-gray-400 transition-transform duration-200 [details[open]_&]:rotate-180"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100">
                @if(count($overview['new_shops']) === 0)
                    <p class="px-5 py-6 text-sm text-gray-400 text-center">今日無新增負責商店</p>
                @else
                    <table class="table">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="px-5 py-3">商店名稱</th>
                                <th class="px-5 py-3">新增時間</th>
                                <th class="px-5 py-3">聯絡人</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($overview['new_shops'] as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-gray-800">
                                        @if($overview['can_edit_shop'])
                                            <a href="{{ route('shops.edit', $row['id']) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $row['name'] }}
                                            </a>
                                        @else
                                            <span>{{ $row['name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">{{ $row['created_at_hm'] }}</td>
                                    <td class="px-5 py-3 text-gray-500">{{ $row['contact'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </details>

        {{-- 全公司今日說明會（預設展開）--}}
        <details open class="bg-white rounded-xl shadow">
            <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none list-none">
                <span class="text-green-400">📋</span>
                <span class="font-semibold text-gray-800">全公司今日說明會</span>
                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                    {{ count($overview['today_conferences']) }} 筆
                </span>
                <svg class="ml-auto w-4 h-4 text-gray-400 transition-transform duration-200 [details[open]_&]:rotate-180"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100">
                @if(count($overview['today_conferences']) === 0)
                    <p class="px-5 py-6 text-sm text-gray-400 text-center">今日無說明會</p>
                @else
                    <table class="table">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="px-5 py-3">說明會名稱</th>
                                <th class="px-5 py-3">時間</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($overview['today_conferences'] as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-gray-800">
                                        @if($overview['can_edit_conference'])
                                            <a href="{{ route('conferences.edit', $row['id']) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $row['name'] }}
                                            </a>
                                        @else
                                            <span>{{ $row['name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">{{ $row['time_range'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </details>

        {{-- 即將到期負責商店（預設收起）--}}
        <details class="bg-white rounded-xl shadow">
            <summary class="flex items-center gap-3 px-5 py-4 cursor-pointer select-none list-none">
                <span class="text-yellow-500">⚠️</span>
                <span class="font-semibold text-gray-800">即將到期負責商店（半年內）</span>
                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                    {{ count($overview['expiring_shops']) }} 筆
                </span>
                <svg class="ml-auto w-4 h-4 text-gray-400 transition-transform duration-200 [details[open]_&]:rotate-180"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="border-t border-gray-100">
                @if(count($overview['expiring_shops']) === 0)
                    <p class="px-5 py-6 text-sm text-gray-400 text-center">暫無半年內到期的負責商店</p>
                @else
                    <table class="table">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="px-5 py-3">商店名稱</th>
                                <th class="px-5 py-3">到期日</th>
                                <th class="px-5 py-3">剩餘天數</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($overview['expiring_shops'] as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-gray-800">
                                        @if($overview['can_edit_shop'])
                                            <a href="{{ route('shops.edit', $row['id']) }}" class="text-blue-600 hover:text-blue-800">
                                                {{ $row['name'] }}
                                            </a>
                                        @else
                                            <span>{{ $row['name'] }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">{{ $row['expired_at'] }}</td>
                                    <td class="px-5 py-3 font-semibold" style="color: {{ $row['color'] }}">
                                        {{ $row['days'] }} 天
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </details>

    </div>
@endsection
