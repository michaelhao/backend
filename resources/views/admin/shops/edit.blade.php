@extends('layouts.admin')

@section('page-title', '編輯商店')

@section('content')
    <div class="mb-6">
        <h2 class="page-title">編輯商店</h2>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('shops.update', $shop) }}"
          data-shop-edit
          data-cert-route="{{ route('shops.certify', $shop) }}"
          data-admin-email-error="{{ $errors->has('admin.email') ? '1' : '' }}">
        @csrf
        @method('PUT')

        {{-- 區塊一：商店基本資料 --}}
        <section class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-base font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-200">商店基本資料</h3>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- name --}}
                <div>
                    <label for="name" class="form-label">商店名稱</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $shop->name) }}"
                           class="w-full rounded-md border @error('name') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- email --}}
                <div>
                    <label for="email" class="form-label">商店信箱</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $shop->email) }}"
                           class="w-full rounded-md border @error('email') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- grade_id --}}
                <div>
                    <label for="grade_id" class="form-label">版本</label>
                    <select id="grade_id" name="grade_id"
                            class="w-full rounded-md border @error('grade_id') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        @foreach ($grades as $grade)
                            <option value="{{ $grade->id }}" {{ old('grade_id', $shop->grade_id) == $grade->id ? 'selected' : '' }}>
                                {{ $grade->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('grade_id')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- status --}}
                <div>
                    <label for="status" class="form-label">狀態</label>
                    <select id="status" name="status"
                            class="w-full rounded-md border @error('status') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        @foreach ($statuses as $statusCase)
                            <option value="{{ $statusCase->value }}" {{ old('status', $shop->status->value) == $statusCase->value ? 'selected' : '' }}>
                                {{ $statusCase->label() }}
                            </option>
                        @endforeach
                    </select>
                    @error('status')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- 區塊二：商店管理員基本資料 --}}
        <section class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-base font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-200">商店管理員資料</h3>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- admin name --}}
                <div>
                    <label for="admin_name" class="form-label">管理員姓名</label>
                    <input id="admin_name" type="text" name="admin[name]" value="{{ old('admin.name', $shop->admin?->name) }}"
                           class="w-full rounded-md border @error('admin.name') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('admin.name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- admin email (masked display + hidden real value + toggle edit) --}}
                <div>
                    <label class="form-label">管理員信箱</label>
                    <div class="flex items-center gap-2">
                        {{-- masked display (read-only, shown by default) --}}
                        <span id="admin-email-masked"
                              class="flex-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 font-mono">
                            {{ \App\Support\Mask::email(old('admin.email', $shop->admin?->email ?? '')) }}
                        </span>
                        {{-- editable input (hidden by default) --}}
                        <input id="admin-email-input" type="email" name="admin[email]"
                               value="{{ old('admin.email', $shop->admin?->email) }}"
                               class="hidden flex-1 rounded-md border @error('admin.email') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        <button type="button" id="admin-email-toggle"
                                class="text-xs text-blue-600 hover:text-blue-800 whitespace-nowrap">
                            修改
                        </button>
                    </div>
                    @error('admin.email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- business_number (readonly masked display + hidden input) --}}
                <div>
                    <label class="form-label">
                        統一編號
                        @if ($shop->admin?->business_number)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">
                                已認證
                            </span>
                        @else
                            <button type="button" id="open-cert-modal"
                                    class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 hover:bg-blue-200 transition-colors cursor-pointer">
                                進行認證
                            </button>
                        @endif
                    </label>
                    <div>
                        <input type="text" id="business-number-display"
                               value="{{ $shop->admin?->business_number ? \App\Support\Mask::string($shop->admin->business_number) : '' }}"
                               readonly placeholder="尚未認證"
                               class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 font-mono">
                        <input type="hidden" id="business-number-hidden" name="admin[business_number]"
                               value="{{ old('admin.business_number', $shop->admin?->business_number) }}">
                    </div>
                    @error('admin.business_number')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- company_name (readonly + hidden input) --}}
                <div>
                    <label class="form-label">公司名稱</label>
                    <input type="text" id="company-name-display"
                           value="{{ old('admin.company_name', $shop->admin?->company_name) }}"
                           readonly placeholder="尚未認證"
                           class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                    <input type="hidden" id="company-name-hidden" name="admin[company_name]"
                           value="{{ old('admin.company_name', $shop->admin?->company_name) }}">
                    @error('admin.company_name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </section>

        {{-- 操作按鈕 --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-blue-600 text-white px-6 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                儲存
            </button>
            <a href="{{ route('shops.index') }}"
               class="text-sm text-gray-500 hover:text-gray-700">取消</a>
        </div>
    </form>

    {{-- 認證 Modal --}}
    <div id="cert-modal" class="modal-overlay hidden">
        <div class="modal-panel">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">商家認證</h3>

            <div class="mb-4">
                <label for="cert-business-number" class="form-label">統一編號（8 位數字）</label>
                <x-form-input id="cert-business-number" maxlength="8" inputmode="numeric" pattern="\d{8}"
                              placeholder="請輸入統一編號"
                              class="w-full font-mono" />
                <p id="cert-input-error" class="mt-1 text-xs text-red-600 hidden">請輸入 8 位數字</p>
            </div>

            <div id="cert-result" class="hidden mb-4 rounded-md p-3 text-sm"></div>

            <div class="modal-actions">
                <button id="cert-modal-close"
                        class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 transition-colors">
                    取消
                </button>
                <button id="cert-submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors">
                    認證
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/shops/edit.js')
@endpush
