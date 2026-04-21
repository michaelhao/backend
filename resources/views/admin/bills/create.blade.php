@extends('layouts.admin')

@section('page-title', '建立帳單')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-800">建立帳單</h2>
        <a href="{{ route('bills.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← 返回列表</a>
    </div>

    <div class="max-w-3xl mx-auto space-y-6 font-[Noto_Sans_TC,sans-serif]">

        {{-- Step 1: 搜尋商店 --}}
        <div id="step-1" class="bg-white rounded-lg shadow p-6">
            <p class="text-lg text-gray-700 mb-1">Hi！{{ Auth::user()->name }}</p>
            <p class="text-gray-500 mb-4">今天你要幫哪間商店處理帳務呢？</p>
            <div class="flex gap-2">
                <input id="shop-keyword" type="text" placeholder="輸入商店 ID、代碼或名稱關鍵字"
                       class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                <button id="shop-search-btn"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    搜尋
                </button>
            </div>
            <div id="shop-dropdown" class="hidden mt-1 border border-gray-200 rounded-lg bg-white shadow-lg max-h-52 overflow-y-auto z-10 relative"></div>
            <div id="shop-selected-info" class="hidden mt-3 flex items-center gap-3">
                <span id="shop-selected-label" class="text-sm text-gray-700 font-medium"></span>
                <button id="shop-confirm-btn"
                        class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm hover:bg-blue-700 transition-colors">
                    確認
                </button>
            </div>
        </div>

        {{-- Step 2: Loading --}}
        <div id="step-2" class="hidden bg-white rounded-lg shadow p-6 flex items-center justify-center gap-3 text-gray-500">
            <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span>驗證商店資訊中…</span>
        </div>

        {{-- Step 3: 商店資訊 + 選擇項目 --}}
        <div id="step-3" class="hidden bg-white rounded-lg shadow p-6">
            <p class="text-lg text-gray-700 mb-4">Hi！{{ Auth::user()->name }}<br><span class="text-gray-500">現在要處理的是</span></p>

            <div id="pending-bill-warning" class="hidden mb-4 rounded-lg bg-orange-50 border border-orange-200 p-3 text-sm text-orange-700"></div>

            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden mb-6">
                <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">商店 ID</th>
                        <th class="px-4 py-2 text-left">商店名稱</th>
                        <th class="px-4 py-2 text-left">版本</th>
                        <th class="px-4 py-2 text-left">狀態</th>
                        <th class="px-4 py-2 text-left">到期日</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t border-gray-200">
                        <td id="info-shop-id" class="px-4 py-3 text-gray-900"></td>
                        <td id="info-shop-name" class="px-4 py-3 text-gray-900 font-medium"></td>
                        <td id="info-shop-grade" class="px-4 py-3 text-gray-600"></td>
                        <td id="info-shop-status" class="px-4 py-3 text-gray-600"></td>
                        <td id="info-shop-expired" class="px-4 py-3 text-gray-600"></td>
                    </tr>
                </tbody>
            </table>

            <p class="text-sm text-gray-600 mb-3">要處理什麼項目呢？</p>
            <div class="flex gap-3">
                <button id="toggle-grade-btn" type="button"
                        class="relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-gray-300 text-gray-600 hover:border-blue-400">
                    版本
                </button>
                <button id="toggle-addon-btn" type="button"
                        class="relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-gray-300 text-gray-600 hover:border-blue-400">
                    Addon
                </button>
            </div>
        </div>

        {{-- Step 4: 設定項目 --}}
        <div id="step-4" class="hidden space-y-4">

            {{-- 版本設定區塊 --}}
            <div id="grade-block" class="hidden bg-white rounded-lg shadow p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">版本設定</h3>

                <div class="flex gap-2 mb-4">
                    <button type="button" data-grade-op="upgrade"
                            class="grade-op-btn px-4 py-1.5 rounded-md border text-sm transition-colors border-gray-300 text-gray-600 hover:border-blue-500">
                        升級
                    </button>
                    <button type="button" data-grade-op="renew"
                            class="grade-op-btn px-4 py-1.5 rounded-md border text-sm transition-colors border-gray-300 text-gray-600 hover:border-blue-500">
                        續約
                    </button>
                    <button type="button" data-grade-op="downgrade"
                            class="grade-op-btn px-4 py-1.5 rounded-md border text-sm transition-colors border-gray-300 text-gray-600 hover:border-blue-500">
                        降級
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">版本</label>
                        <select id="grade-select" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none">
                            <option value="">— 請選擇版本 —</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">開始日</label>
                        <input id="grade-start-at" type="date"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">購買月數</label>
                        <select id="grade-months" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none">
                            <option value="">— 請選擇 —</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">金額</label>
                        <input id="grade-amount" type="text" readonly placeholder="自動計算"
                               class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">預計到期日</label>
                        <input id="grade-expired-at" type="text" readonly placeholder="自動計算"
                               class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    </div>
                </div>

                <div id="grade-overlap-warning" class="hidden mt-3 p-3 rounded-lg bg-orange-50 border border-orange-200 text-sm text-orange-700"></div>
            </div>

            {{-- Addon 設定區塊 --}}
            <div id="addon-block" class="hidden bg-white rounded-lg shadow p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">Addon 設定</h3>

                <div id="addon-rows" class="space-y-3 mb-3"></div>

                <button type="button" id="add-addon-row-btn"
                        class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                    + 新增項目
                </button>
            </div>

            {{-- 折抵區塊 --}}
            <div id="discount-block" class="hidden bg-white rounded-lg shadow p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">折抵</h3>
                <div class="flex gap-3 items-end">
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">折抵方案</label>
                        <select id="discount-type" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none">
                            <option value="">— 選擇方案 —</option>
                            @foreach ($discounts as $d)
                                <option value="{{ $d->id }}" data-name="{{ $d->name }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-xs text-gray-500 mb-1">折抵金額</label>
                        <input id="discount-amount" type="number" min="0" placeholder="0" disabled
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none disabled:bg-gray-50">
                    </div>
                </div>
                <div id="discount-info" class="hidden mt-2 p-2 rounded bg-orange-50 text-xs text-orange-700"></div>
                <p id="discount-error" class="hidden mt-1 text-xs text-red-600"></p>
            </div>

            {{-- Order Summary --}}
            <div id="order-summary" class="hidden bg-white rounded-lg shadow p-6">
                <h3 class="text-base font-semibold text-gray-800 mb-4">帳單明細</h3>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 border-b border-gray-200">
                            <th class="pb-2 text-left font-medium">項目</th>
                            <th class="pb-2 text-left font-medium">期間</th>
                            <th class="pb-2 text-right font-medium">金額</th>
                        </tr>
                    </thead>
                    <tbody id="summary-rows" class="divide-y divide-gray-100"></tbody>
                </table>
                <div class="mt-3 pt-3 border-t border-gray-200 space-y-1 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>小計</span>
                        <span id="summary-subtotal"></span>
                    </div>
                    <div id="summary-discount-row" class="hidden flex justify-between text-orange-600">
                        <span>折抵</span>
                        <span id="summary-discount-val"></span>
                    </div>
                    <div class="flex justify-between font-semibold text-gray-900 text-base">
                        <span>合計</span>
                        <span id="summary-total"></span>
                    </div>
                </div>
            </div>

            {{-- 送出 --}}
            <div id="submit-block" class="hidden">
                <form id="bill-form" method="POST" action="{{ route('bills.store') }}">
                    @csrf
                    <input type="hidden" id="form-shop-id" name="shop_id">
                    <div id="form-details-container"></div>
                    <input type="hidden" id="form-discount-amount" name="discount_amount">
                    <input type="hidden" id="form-discount-name" name="discount_name">
                    <button type="submit"
                            class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium text-sm hover:bg-blue-700 transition-colors">
                        送出帳單
                    </button>
                </form>
                @if ($errors->any())
                    <div class="mt-2 p-3 rounded-lg bg-red-50 text-sm text-red-600">
                        <ul class="list-disc list-inside space-y-0.5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        window.billConfig = {
            shopSearchUrl: '{{ route('bills.shop-search') }}',
            shopInfoUrl: '{{ route('bills.shop-info') }}',
            calculateUrl: '{{ route('bills.calculate') }}',
            today: '{{ now()->toDateString() }}',
        };
    </script>
@endsection

@push('scripts')
    @vite('resources/js/bills/create.js')
@endpush
