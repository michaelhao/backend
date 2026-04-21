@extends('layouts.admin')

@section('page-title', '帳務管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">帳務管理</h2>
        <x-permission name="Bill.create">
            <a href="{{ route('bills.create') }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                建立帳單
            </a>
        </x-permission>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-700 flash-message">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700 flash-message">{{ session('error') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-6 py-3">帳單編號</th>
                    <th class="px-6 py-3">商店</th>
                    <th class="px-6 py-3">版本小計</th>
                    <th class="px-6 py-3">加購小計</th>
                    <th class="px-6 py-3">合計</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">建立時間</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($bills as $bill)
                    <tr class="hover:bg-gray-50" data-bill-id="{{ $bill->id }}">
                        <td class="px-6 py-4 font-mono text-gray-600 text-xs">{{ $bill->no }}</td>
                        <td class="px-6 py-4 text-gray-900">{{ $bill->shop->name }}</td>
                        <td class="px-6 py-4 text-gray-600">NT${{ number_format($bill->total_grade) }}</td>
                        <td class="px-6 py-4 text-gray-600">NT${{ number_format($bill->total_addons) }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">NT${{ number_format($bill->total) }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusMap = [
                                    \App\Enums\BillPaymentStatus::Pending->value  => ['label' => '待審核', 'class' => 'bg-yellow-100 text-yellow-800'],
                                    \App\Enums\BillPaymentStatus::Unpaid->value   => ['label' => '未付款', 'class' => 'bg-orange-100 text-orange-800'],
                                    \App\Enums\BillPaymentStatus::Paid->value     => ['label' => '已付款', 'class' => 'bg-green-100 text-green-800'],
                                    \App\Enums\BillPaymentStatus::Invalid->value  => ['label' => '已失效', 'class' => 'bg-gray-100 text-gray-500'],
                                ];
                                $s = $statusMap[$bill->payment_status->value] ?? ['label' => '未知', 'class' => 'bg-gray-100 text-gray-500'];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['class'] }}">
                                {{ $s['label'] }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500 text-xs">{{ $bill->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-4 space-x-2">
                            @if (in_array($bill->payment_status->value, [\App\Enums\BillPaymentStatus::Pending->value, \App\Enums\BillPaymentStatus::Unpaid->value]))
                                <x-permission name="Bill.pay">
                                    <button type="button"
                                            class="pay-btn text-green-600 hover:text-green-800 text-xs"
                                            data-bill-id="{{ $bill->id }}"
                                            data-bill-no="{{ $bill->no }}">
                                        付款
                                    </button>
                                </x-permission>
                                <x-permission name="Bill.writeoff">
                                    <button type="button"
                                            class="writeoff-btn text-red-600 hover:text-red-800 text-xs"
                                            data-bill-id="{{ $bill->id }}"
                                            data-bill-no="{{ $bill->no }}">
                                        銷帳
                                    </button>
                                </x-permission>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-400">尚無帳單記錄</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- 銷帳 Modal --}}
    <div id="writeoff-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-1">銷帳</h3>
            <p id="writeoff-modal-no" class="text-xs text-gray-500 font-mono mb-4"></p>
            <hr class="mb-4">
            <div id="writeoff-detail-list" class="space-y-2 mb-4"></div>
            <hr class="mb-4">
            <div class="flex justify-end gap-3">
                <button id="writeoff-cancel" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">取消</button>
                <button id="writeoff-confirm" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">進行銷帳</button>
            </div>
        </div>
    </div>

    {{-- 付款確認 Modal --}}
    <div id="pay-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">確認付款</h3>
            <p class="text-sm text-gray-600 mb-6">確定要將帳單 <span id="pay-modal-no" class="font-mono font-medium"></span> 標記為已付款嗎？</p>
            <div class="flex justify-end gap-3">
                <button id="pay-cancel" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">取消</button>
                <button id="pay-confirm" class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 transition-colors">確認付款</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/bills/index.js')
@endpush
