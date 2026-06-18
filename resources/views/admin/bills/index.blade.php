@extends('layouts.admin')

@section('page-title', '帳務管理')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="page-title">帳務管理</h2>
        <x-permission name="Bill.create">
            <a href="{{ route('bills.create') }}"
               class="btn-primary">
                建立帳單
            </a>
        </x-permission>
    </div>

    @if (session('success'))
        <div class="flash flash-success flash-message">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="flash flash-error flash-message">{{ session('error') }}</div>
    @endif

    {{-- 搜尋列 --}}
    <form method="GET" action="{{ route('bills.index') }}" class="mb-4 bg-white rounded-lg shadow p-4 flex flex-wrap gap-3 items-end">
        <div class="flex flex-col gap-1">
            <label for="no" class="text-xs text-gray-500">帳單編號</label>
            <input id="no" type="text" name="no" value="{{ $filters['no'] ?? '' }}"
                   placeholder="模糊搜尋"
                   class="border border-gray-300 rounded-md px-3 py-1.5 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
            <label for="payment_status" class="text-xs text-gray-500">狀態</label>
            <select id="payment_status" name="payment_status"
                    class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="1" {{ ($filters['payment_status'] ?? '') == '1' ? 'selected' : '' }}>待審核</option>
                <option value="2" {{ ($filters['payment_status'] ?? '') == '2' ? 'selected' : '' }}>待付款</option>
                <option value="3" {{ ($filters['payment_status'] ?? '') == '3' ? 'selected' : '' }}>已付款</option>
                <option value="4" {{ ($filters['payment_status'] ?? '') == '4' ? 'selected' : '' }}>已失效</option>
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="sales_id" class="text-xs text-gray-500">負責業務</label>
            <select id="sales_id" name="sales_id"
                    class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                @foreach ($salesUsers as $user)
                    <option value="{{ $user->id }}" {{ ($filters['sales_id'] ?? '') == $user->id ? 'selected' : '' }}>
                        {{ $user->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col gap-1">
            <label for="payment_method" class="text-xs text-gray-500">付款方式</label>
            <select id="payment_method" name="payment_method"
                    class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">全部</option>
                <option value="1" {{ ($filters['payment_method'] ?? '') == '1' ? 'selected' : '' }}>信用卡</option>
                <option value="2" {{ ($filters['payment_method'] ?? '') == '2' ? 'selected' : '' }}>轉帳</option>
                <option value="3" {{ ($filters['payment_method'] ?? '') == '3' ? 'selected' : '' }}>現金</option>
            </select>
        </div>
        <button type="submit"
                class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700 transition-colors">
            搜尋
        </button>
        @if (array_filter($filters))
            <a href="{{ route('bills.index') }}" class="px-4 py-1.5 text-sm text-gray-500 hover:text-gray-700">清除</a>
        @endif
    </form>

    <div class="card">
        <table class="table">
            <thead class="table-head">
                <tr>
                    <th class="px-6 py-3">帳單編號</th>
                    <th class="px-6 py-3">商店</th>
                    <th class="px-6 py-3">負責業務</th>
                    <th class="px-6 py-3">合計</th>
                    <th class="px-6 py-3">付款方式</th>
                    <th class="px-6 py-3">狀態</th>
                    <th class="px-6 py-3">建立時間</th>
                    <th class="px-6 py-3">操作</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($bills as $bill)
                    <tr class="hover:bg-gray-50" data-bill-id="{{ $bill->id }}">
                        <td class="px-6 py-4">
                            <button type="button"
                                    class="detail-btn font-mono text-blue-600 hover:text-blue-800 text-xs underline underline-offset-2 cursor-pointer"
                                    data-bill-id="{{ $bill->id }}"
                                    data-bill-no="{{ $bill->no }}">
                                {{ $bill->no }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-gray-900">{{ $bill->shop->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $bill->shopSales?->name ?? '—' }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">NT${{ number_format($bill->total) }}</td>
                        <td class="px-6 py-4 text-gray-500">
                            @php
                                $methodMap = [1 => '信用卡', 2 => '轉帳', 3 => '現金'];
                            @endphp
                            {{ $methodMap[$bill->payment_method?->value] ?? '—' }}
                        </td>
                        <td class="px-6 py-4">
                            @php
                                $statusMap = [
                                    \App\Enums\BillPaymentStatus::Pending->value  => ['label' => '待審核', 'class' => 'bg-yellow-100 text-yellow-800'],
                                    \App\Enums\BillPaymentStatus::Unpaid->value   => ['label' => '待付款', 'class' => 'bg-orange-100 text-orange-800'],
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
                                <x-permission name="Bill.writeoff">
                                    <button type="button"
                                            class="writeoff-btn text-red-600 hover:text-red-800 text-xs"
                                            data-bill-id="{{ $bill->id }}"
                                            data-bill-no="{{ $bill->no }}">
                                        銷帳
                                    </button>
                                </x-permission>
                            @endif
                            <x-permission name="Bill.pay">
                                <button type="button"
                                        class="edit-btn text-blue-600 hover:text-blue-800 text-xs"
                                        data-bill-id="{{ $bill->id }}"
                                        data-bill-no="{{ $bill->no }}"
                                        data-payment-status="{{ $bill->payment_status->value }}"
                                        data-paid-at="{{ $bill->paid_at?->format('Y-m-d') ?? '' }}"
                                        data-invoice-no="{{ $bill->invoice_no ?? '' }}">
                                    編輯帳務
                                </button>
                            </x-permission>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-gray-400">尚無帳單記錄</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if ($bills->hasPages())
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $bills->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    {{-- Vue 島嶼：四個 modal 整合 --}}
    <div id="bills-modals"></div>
@endsection

@push('scripts')
    @vite('resources/js/bills/index.js')
@endpush
