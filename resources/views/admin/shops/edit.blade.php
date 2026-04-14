@extends('layouts.admin')

@section('page-title', '編輯商店')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-800">編輯商店</h2>
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

    <form method="POST" action="{{ route('shops.update', $shop) }}">
        @csrf
        @method('PUT')

        {{-- 區塊一：商店基本資料 --}}
        <section class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-base font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-200">商店基本資料</h3>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">商店名稱</label>
                    <input id="name" type="text" name="name" value="{{ old('name', $shop->name) }}"
                           class="w-full rounded-md border @error('name') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">商店信箱</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $shop->email) }}"
                           class="w-full rounded-md border @error('email') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('email')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- grade_id --}}
                <div>
                    <label for="grade_id" class="block text-sm font-medium text-gray-700 mb-1">版本</label>
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
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">狀態</label>
                    @php
                        $statusLabels = [
                            \App\Enums\ShopStatus::Active->value   => '啟用',
                            \App\Enums\ShopStatus::Closed->value   => '關閉',
                            \App\Enums\ShopStatus::Expired->value  => '過期',
                            \App\Enums\ShopStatus::Archived->value => '封存',
                        ];
                    @endphp
                    <select id="status" name="status"
                            class="w-full rounded-md border @error('status') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                        @foreach ($statuses as $statusCase)
                            <option value="{{ $statusCase->value }}" {{ old('status', $shop->status->value) == $statusCase->value ? 'selected' : '' }}>
                                {{ $statusLabels[$statusCase->value] }}
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
                    <label for="admin_name" class="block text-sm font-medium text-gray-700 mb-1">管理員姓名</label>
                    <input id="admin_name" type="text" name="admin[name]" value="{{ old('admin.name', $shop->admin?->name) }}"
                           class="w-full rounded-md border @error('admin.name') border-red-400 @else border-gray-300 @enderror px-3 py-2 text-sm focus:border-blue-500 focus:outline-none">
                    @error('admin.name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- admin email (masked display + hidden real value + toggle edit) --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">管理員信箱</label>
                    <div class="flex items-center gap-2">
                        {{-- masked display (read-only, shown by default) --}}
                        <span id="admin-email-masked"
                              class="flex-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 font-mono">
                            {{ maskEmail(old('admin.email', $shop->admin?->email ?? '')) }}
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">
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
                               value="{{ $shop->admin?->business_number ? maskString($shop->admin->business_number) : '' }}"
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">公司名稱</label>
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
    <div id="cert-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">商家認證</h3>

            <div class="mb-4">
                <label for="cert-business-number" class="block text-sm font-medium text-gray-700 mb-1">統一編號（8 位數字）</label>
                <input id="cert-business-number" type="text" maxlength="8" inputmode="numeric" pattern="\d{8}"
                       placeholder="請輸入統一編號"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none">
                <p id="cert-input-error" class="mt-1 text-xs text-red-600 hidden">請輸入 8 位數字</p>
            </div>

            <div id="cert-result" class="hidden mb-4 rounded-md p-3 text-sm"></div>

            <div class="flex justify-end gap-3">
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
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Admin email toggle
        const emailMasked = document.getElementById('admin-email-masked');
        const emailInput  = document.getElementById('admin-email-input');
        const emailToggle = document.getElementById('admin-email-toggle');

        if (emailToggle) {
            emailToggle.addEventListener('click', function () {
                if (emailInput.classList.contains('hidden')) {
                    emailMasked.classList.add('hidden');
                    emailInput.classList.remove('hidden');
                    emailToggle.textContent = '取消';
                    emailInput.focus();
                } else {
                    emailMasked.classList.remove('hidden');
                    emailInput.classList.add('hidden');
                    emailToggle.textContent = '修改';
                }
            });
        }

        // If there were validation errors on admin.email, show the input immediately
        @if ($errors->has('admin.email'))
            if (emailToggle) emailToggle.click();
        @endif

        // Certification modal
        const certModal    = document.getElementById('cert-modal');
        const openCertBtn  = document.getElementById('open-cert-modal');
        const certClose    = document.getElementById('cert-modal-close');
        const certSubmit   = document.getElementById('cert-submit');
        const certInput    = document.getElementById('cert-business-number');
        const certResult   = document.getElementById('cert-result');
        const certError    = document.getElementById('cert-input-error');

        function closeCertModal() {
            certModal.classList.add('hidden');
        }

        if (openCertBtn) {
            openCertBtn.addEventListener('click', () => {
                certInput.value = '';
                certResult.classList.add('hidden');
                certError.classList.add('hidden');
                certSubmit.disabled = false;
                certSubmit.textContent = '認證';
                certModal.classList.remove('hidden');
            });
        }

        certClose?.addEventListener('click', closeCertModal);
        certModal?.addEventListener('click', e => { if (e.target === certModal) closeCertModal(); });

        // Only allow digits in cert input
        certInput?.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });

        certSubmit?.addEventListener('click', async function () {
            const bn = certInput.value.trim();
            if (!/^\d{8}$/.test(bn)) {
                certError.classList.remove('hidden');
                return;
            }
            certError.classList.add('hidden');

            certSubmit.disabled = true;
            certSubmit.textContent = '認證中...';
            certResult.classList.add('hidden');

            try {
                const response = await fetch('{{ route('shops.certify', $shop) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ business_number: bn }),
                });

                const data = await response.json();

                if (data.success) {
                    // Update hidden inputs and display fields
                    document.getElementById('business-number-hidden').value = bn;
                    document.getElementById('business-number-display').value = maskString(bn);
                    document.getElementById('company-name-hidden').value = data.company_name;
                    document.getElementById('company-name-display').value = data.company_name;

                    certResult.className = 'mb-4 rounded-md p-3 text-sm bg-green-50 text-green-700';
                    certResult.innerHTML = `<strong>認證成功</strong><br>公司名稱：${data.company_name}<br><span class="text-xs text-green-600 mt-1 block">請儲存商店資料以完成認證流程</span>`;
                    certResult.classList.remove('hidden');
                    certSubmit.disabled = false;
                    certSubmit.textContent = '完成';
                    certSubmit.onclick = closeCertModal;
                } else {
                    certResult.className = 'mb-4 rounded-md p-3 text-sm bg-red-50 text-red-700';
                    certResult.textContent = '認證失敗，請確認統一編號是否正確';
                    certResult.classList.remove('hidden');
                    certSubmit.disabled = false;
                    certSubmit.textContent = '認證';
                }
            } catch (e) {
                certResult.className = 'mb-4 rounded-md p-3 text-sm bg-red-50 text-red-700';
                certResult.textContent = '認證失敗，請確認統一編號是否正確';
                certResult.classList.remove('hidden');
                certSubmit.disabled = false;
                certSubmit.textContent = '認證';
            }
        });

        // Helper: mask string (mirrors PHP maskString logic)
        function maskString(value) {
            return value.split('').map((c, i) => i % 2 === 1 ? '*' : c).join('');
        }
    });
</script>
@endpush
