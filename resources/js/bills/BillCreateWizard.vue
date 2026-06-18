<template>
    <!-- Step 1: 搜尋商店 -->
    <div id="step-1" v-show="step === 1" class="bg-white rounded-lg shadow p-6">
        <p class="text-lg text-gray-700 mb-1">Hi！{{ userName }}</p>
        <p class="text-gray-500 mb-4">今天你要幫哪間商店處理帳務呢？</p>
        <div class="flex gap-2">
            <input
                id="shop-keyword"
                v-model="keyword"
                @input="onKeywordInput"
                type="text"
                placeholder="輸入商店 ID、代碼或名稱關鍵字"
                class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
            />
            <button id="shop-search-btn" type="button" @click="doSearch(keyword)" class="btn-primary">
                搜尋
            </button>
        </div>
        <div
            id="shop-dropdown"
            v-show="dropdownOpen"
            class="mt-1 border border-gray-200 rounded-lg bg-white shadow-lg max-h-52 overflow-y-auto z-10 relative"
        >
            <div v-if="searchCandidates.length === 0" class="px-4 py-2 text-sm text-gray-400">找不到符合的商店</div>
            <div
                v-for="s in searchCandidates"
                :key="s.id"
                class="px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 cursor-pointer shop-option"
                :data-id="s.id"
                :data-label="s.label"
                @click="selectShop(s)"
            >{{ s.label }}</div>
        </div>
        <div v-show="selectedShopLabel" id="shop-selected-info" class="mt-3 flex items-center gap-3">
            <span id="shop-selected-label" class="text-sm text-gray-700 font-medium">{{ selectedShopLabel }}</span>
            <button
                id="shop-confirm-btn"
                type="button"
                @click="confirmShop"
                class="bg-blue-600 text-white px-3 py-1.5 rounded-md text-sm hover:bg-blue-700 transition-colors"
            >確認</button>
        </div>
    </div>

    <!-- Step 2: Loading -->
    <div id="step-2" v-show="step === 2" class="bg-white rounded-lg shadow p-6 flex items-center justify-center gap-3 text-gray-500">
        <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
        </svg>
        <span>驗證商店資訊中…</span>
    </div>

    <!-- Step 3: 商店資訊 + 選擇項目 -->
    <div id="step-3" v-show="step >= 3" class="bg-white rounded-lg shadow p-6">
        <p class="text-lg text-gray-700 mb-4">Hi！{{ userName }}<br><span class="text-gray-500">現在要處理的是</span></p>

        <div
            id="pending-bill-warning"
            v-show="shopData && shopData.pending_bill_count > 0"
            class="mb-4 rounded-lg bg-orange-50 border border-orange-200 p-3 text-sm text-orange-700"
        >
            ⚠ 此商店有 {{ shopData?.pending_bill_count }} 張待處理帳單，建議先完成付款或銷帳後再建立新帳單，以避免升級殘值計算錯誤。
        </div>

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
                    <td id="info-shop-id" class="px-4 py-3 text-gray-900">{{ shopData?.shop?.id }}</td>
                    <td id="info-shop-name" class="px-4 py-3 text-gray-900 font-medium">{{ shopData?.shop?.name }}</td>
                    <td id="info-shop-grade" class="px-4 py-3 text-gray-600">{{ shopData?.shop?.grade || '—' }}</td>
                    <td id="info-shop-status" class="px-4 py-3 text-gray-600">{{ shopData?.shop?.status }}</td>
                    <td id="info-shop-expired" class="px-4 py-3 text-gray-600">{{ shopData?.shop?.expired_at ? shopData.shop.expired_at.substring(0, 10) : '—' }}</td>
                </tr>
            </tbody>
        </table>

        <p class="text-sm text-gray-600 mb-3">要處理什麼項目呢？</p>
        <div class="flex gap-3">
            <button
                id="toggle-grade-btn"
                type="button"
                @click="toggleGrade"
                :class="gradeEnabled
                    ? 'relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-blue-500 text-blue-600 bg-blue-50 hover:border-blue-400'
                    : 'relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-gray-300 text-gray-600 hover:border-blue-400'"
            >
                版本 <span v-if="gradeEnabled" class="text-blue-500">✓</span>
            </button>
            <button
                id="toggle-addon-btn"
                type="button"
                @click="toggleAddon"
                :class="addonEnabled
                    ? 'relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-blue-500 text-blue-600 bg-blue-50 hover:border-blue-400'
                    : 'relative px-5 py-2 rounded-lg border text-sm font-medium transition-colors border-gray-300 text-gray-600 hover:border-blue-400'"
            >
                Addon <span v-if="addonEnabled" class="text-blue-500">✓</span>
            </button>
        </div>
    </div>

    <!-- Step 4: 設定項目 -->
    <div id="step-4" v-show="step >= 3 && (gradeEnabled || addonEnabled)" class="space-y-4">

        <!-- 版本設定區塊 -->
        <div id="grade-block" v-show="gradeEnabled" class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">版本設定</h3>

            <div class="flex gap-2 mb-4">
                <button
                    v-for="op in ['upgrade', 'renew', 'downgrade']"
                    :key="op"
                    type="button"
                    :data-grade-op="op"
                    @click="selectGradeOp(op)"
                    :class="gradeOp === op
                        ? 'grade-op-btn px-4 py-1.5 rounded-md border text-sm transition-colors border-blue-500 text-blue-600 bg-blue-50 hover:border-blue-500'
                        : 'grade-op-btn px-4 py-1.5 rounded-md border text-sm transition-colors border-gray-300 text-gray-600 hover:border-blue-500'"
                >{{ { upgrade: '升級', renew: '續約', downgrade: '降級' }[op] }}</button>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">版本</label>
                    <select
                        id="grade-select"
                        v-model="gradeForm.gradeId"
                        @change="onGradeSelectChange"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
                    >
                        <option value="">— 請選擇版本 —</option>
                        <option
                            v-for="g in filteredGrades"
                            :key="g.id"
                            :value="g.id"
                            :data-price="g.price"
                            :data-weight="g.weight"
                            :data-name="g.name"
                        >{{ g.name }}（NT${{ Number(g.price).toLocaleString() }}/月）</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">開始日</label>
                    <input
                        id="grade-start-at"
                        type="date"
                        v-model="gradeForm.startAt"
                        :min="gradeOp === 'upgrade' ? today : undefined"
                        :readonly="gradeOp !== 'upgrade'"
                        @change="onGradeStartAtChange"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
                    />
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">購買月數</label>
                    <select
                        id="grade-months"
                        v-model="gradeForm.months"
                        @change="triggerGradeCalculate"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
                    >
                        <option value="">— 請選擇 —</option>
                        <option v-for="o in gradeMonthsOptions" :key="o.v" :value="String(o.v)">{{ o.l }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">金額</label>
                    <input
                        id="grade-amount"
                        type="text"
                        readonly
                        :value="gradeAmount"
                        placeholder="自動計算"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                    />
                </div>
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 mb-1">預計到期日</label>
                    <input
                        id="grade-expired-at"
                        type="text"
                        readonly
                        :value="gradeExpiredAt"
                        placeholder="自動計算"
                        class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700"
                    />
                </div>
            </div>

            <div
                id="grade-overlap-warning"
                v-show="gradeOverlapWarning"
                class="mt-3 p-3 rounded-lg bg-orange-50 border border-orange-200 text-sm text-orange-700"
            >{{ gradeOverlapWarning }}</div>
        </div>

        <!-- Addon 設定區塊 -->
        <div id="addon-block" v-show="addonEnabled" class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">Addon 設定</h3>

            <div id="addon-rows" class="space-y-3 mb-3">
                <div
                    v-for="row in addonRows"
                    :key="row.id"
                    class="addon-row border border-gray-100 rounded-lg p-3 bg-gray-50"
                    :data-row-id="row.id"
                >
                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <label class="text-xs text-gray-500 mb-1 block">加購項目</label>
                            <select
                                class="addon-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
                                v-model="row.addonId"
                                @change="onAddonSelectChange(row)"
                            >
                                <option value="">— 選擇 Addon —</option>
                                <option
                                    v-for="a in getAddonOptions(row)"
                                    :key="a.id"
                                    :value="String(a.id)"
                                    :data-price="a.price"
                                    :data-name="a.name"
                                    :data-type="a.type"
                                    :data-in-grade="a.inGrade ? '1' : '0'"
                                    :disabled="a.disabled"
                                >{{ a.label }}</option>
                            </select>
                        </div>
                        <button type="button" class="remove-addon-row text-gray-400 hover:text-red-500 text-lg leading-none pb-2" @click="removeAddonRow(row)">✕</button>
                    </div>
                    <div class="grid grid-cols-4 gap-2 mt-2">
                        <div :class="row.isQuota ? 'addon-qty-col' : 'addon-qty-col hidden'">
                            <label class="text-xs text-gray-500 mb-1 block">數量</label>
                            <input
                                type="number"
                                min="1"
                                v-model.number="row.qty"
                                @input="triggerAddonCalculate(row)"
                                class="addon-qty w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">開始日</label>
                            <input
                                type="date"
                                v-model="row.startAt"
                                :min="today"
                                @change="onAddonStartAtChange(row)"
                                class="addon-start-at w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none"
                            />
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">月數</label>
                            <select
                                class="addon-months w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none"
                                v-model="row.months"
                                @change="triggerAddonCalculate(row)"
                            >
                                <option value="">—</option>
                                <option v-for="o in row.monthsOptions" :key="o.v" :value="String(o.v)">{{ o.l }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">金額</label>
                            <input
                                type="text"
                                readonly
                                :value="row.amount"
                                class="addon-amount w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700"
                                placeholder="自動"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="add-addon-row-btn" @click="addAddonRow" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                + 新增項目
            </button>
        </div>

        <!-- 折抵區塊 -->
        <div id="discount-block" v-show="subtotal > 0" class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">折抵</h3>
            <div class="flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-xs text-gray-500 mb-1">折抵方案</label>
                    <select
                        id="discount-type"
                        v-model="discount.typeId"
                        @change="onDiscountTypeChange"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
                    >
                        <option value="">— 選擇方案 —</option>
                        <option v-for="d in discounts" :key="d.id" :value="String(d.id)" :data-name="d.name">{{ d.name }}</option>
                    </select>
                </div>
                <div class="flex-1">
                    <label class="block text-xs text-gray-500 mb-1">折抵金額</label>
                    <input
                        id="discount-amount"
                        type="number"
                        min="0"
                        placeholder="0"
                        :disabled="!discount.typeId"
                        v-model.number="discount.amount"
                        @input="onDiscountAmountInput"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none disabled:bg-gray-50"
                    />
                </div>
            </div>
            <div id="discount-info" v-show="discountInfo" class="mt-2 p-2 rounded bg-orange-50 text-xs text-orange-700">{{ discountInfo }}</div>
            <p id="discount-error" v-show="discountError" class="mt-1 text-xs text-red-600">{{ discountError }}</p>
        </div>

        <!-- Order Summary -->
        <div id="order-summary" v-show="allDetails.length > 0" class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">帳單明細</h3>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-200">
                        <th class="pb-2 text-left font-medium">項目</th>
                        <th class="pb-2 text-left font-medium">期間</th>
                        <th class="pb-2 text-right font-medium">金額</th>
                    </tr>
                </thead>
                <tbody id="summary-rows" class="divide-y divide-gray-100">
                    <tr v-for="(d, i) in allDetails" :key="i">
                        <td class="py-1.5 text-gray-700">{{ d.name }}</td>
                        <td class="py-1.5 text-gray-500 text-xs">{{ d.start_at?.substring(0, 10) }} → {{ d.expired_at?.substring(0, 10) }}</td>
                        <td class="py-1.5 text-right font-medium text-gray-900">{{ fmt(d.total_price) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="mt-3 pt-3 border-t border-gray-200 space-y-1 text-sm">
                <div class="flex justify-between text-gray-600">
                    <span>小計</span>
                    <span id="summary-subtotal">{{ fmt(subtotal) }}</span>
                </div>
                <div id="summary-discount-row" v-show="effectiveDiscount > 0" class="flex justify-between text-orange-600">
                    <span>折抵</span>
                    <span id="summary-discount-val">−{{ fmt(effectiveDiscount) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-gray-900 text-base">
                    <span>合計</span>
                    <span id="summary-total">{{ fmt(total) }}</span>
                </div>
            </div>
        </div>

        <!-- 付款方式 -->
        <div id="payment-method-block" v-show="allDetails.length > 0" class="bg-white rounded-lg shadow p-6">
            <h3 class="text-base font-semibold text-gray-800 mb-4">付款方式</h3>
            <select
                id="payment-method-select"
                v-model="paymentMethod"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none"
            >
                <option value="2">匯款轉帳</option>
            </select>
        </div>

        <!-- 送出 — hidden inputs + submit button (parent Blade form wraps this island) -->
        <div id="submit-block" v-show="allDetails.length > 0">
            <!-- Hidden fields submitted with the parent form -->
            <input type="hidden" name="shop_id" :value="shopData?.shop?.id ?? ''" />
            <input type="hidden" name="payment_method" :value="paymentMethod" />
            <!-- details[i][k] hidden inputs -->
            <template v-for="(d, i) in allDetails" :key="i">
                <template v-for="k in detailKeys" :key="k">
                    <input
                        v-if="d[k] != null"
                        type="hidden"
                        :name="`details[${i}][${k}]`"
                        :value="d[k]"
                    />
                </template>
            </template>
            <input type="hidden" name="discount_amount" :value="effectiveDiscount > 0 ? effectiveDiscount : ''" />
            <input type="hidden" name="discount_id" :value="effectiveDiscount > 0 ? discount.typeId : ''" />
            <button
                type="submit"
                @click.prevent="onSubmit"
                class="w-full bg-blue-600 text-white py-3 rounded-lg font-medium text-sm hover:bg-blue-700 transition-colors"
            >
                送出帳單
            </button>
        </div>

    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import http from '@/lib/http';
import { useFlash } from '@/composables/useFlash';
import { fmt, monthsOptions, paymentTypeFromMonths } from './billMath';

const props = defineProps({
    shopSearchUrl: { type: String, required: true },
    shopInfoUrl:   { type: String, required: true },
    calculateUrl:  { type: String, required: true },
    today:         { type: String, required: true },
    formAction:    { type: String, default: '' },
    discounts:     { type: Array, default: () => [] },
    userName:      { type: String, default: '' },
});

const { showFlash } = useFlash();

// ─── Step state ───────────────────────────────────────────────
const step = ref(1);

// ─── Step 1: search ───────────────────────────────────────────
const keyword         = ref('');
const searchCandidates = ref([]);
const dropdownOpen    = ref(false);
const selectedShopId  = ref(null);
const selectedShopLabel = ref('');
let searchTimeout = null;

function onKeywordInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => doSearch(keyword.value), 300);
}

async function doSearch(kw) {
    if (!kw.trim()) { dropdownOpen.value = false; return; }
    try {
        const res = await http.get(props.shopSearchUrl, { params: { keyword: kw } });
        searchCandidates.value = res.data.shops ?? [];
        dropdownOpen.value = true;
    } catch {
        dropdownOpen.value = false;
    }
}

function selectShop(s) {
    selectedShopId.value = parseInt(s.id);
    selectedShopLabel.value = s.label;
    dropdownOpen.value = false;
}

function onDocumentClick(e) {
    const dropdown = document.getElementById('shop-dropdown');
    const keywordEl = document.getElementById('shop-keyword');
    const searchBtn = document.getElementById('shop-search-btn');
    if (dropdown && !dropdown.contains(e.target) && e.target !== keywordEl && e.target !== searchBtn) {
        dropdownOpen.value = false;
    }
}

// ─── Step 1 → 2 → 3 ──────────────────────────────────────────
const shopData = ref(null);

async function confirmShop() {
    if (!selectedShopId.value) return;
    step.value = 2;
    try {
        const res = await http.get(props.shopInfoUrl, { params: { shop_id: selectedShopId.value } });
        shopData.value = res.data;
        step.value = 3;
    } catch (err) {
        step.value = 1;
        showFlash('error', err.response?.data?.message || '無法載入商店資訊');
    }
}

// ─── Step 3: toggle grade/addon ───────────────────────────────
const gradeEnabled = ref(false);
const addonEnabled = ref(false);

function toggleGrade() {
    gradeEnabled.value = !gradeEnabled.value;
    if (gradeEnabled.value) {
        initGradeBlock();
    } else {
        gradeDetails.value = [];
    }
}

function toggleAddon() {
    addonEnabled.value = !addonEnabled.value;
    if (addonEnabled.value && addonRows.value.length === 0) {
        addAddonRow();
    }
    // Keep rows on toggle-off (mirrors original hide behaviour) so re-enabling restores them
}

// ─── Grade Block ──────────────────────────────────────────────
const gradeOp      = ref('upgrade');
const gradeForm    = ref({ gradeId: '', startAt: '', months: '' });
const gradeAmount  = ref('');
const gradeExpiredAt = ref('');
const gradeOverlapWarning = ref('');
const gradeDetails = ref([]);

const filteredGrades = computed(() => {
    const grades = shopData.value?.grades ?? [];
    const currentWeight = shopData.value?.shop?.grade_weight ?? 0;
    const op = gradeOp.value;
    let filtered = grades.filter(g => {
        if (op === 'upgrade')   return g.weight > currentWeight;
        if (op === 'downgrade') return g.weight < currentWeight;
        if (op === 'renew')     return g.weight === currentWeight;
        return false;
    });
    if (op === 'upgrade') filtered = [...filtered].sort((a, b) => b.weight - a.weight);
    return filtered;
});

const gradeMonthsOptions = computed(() => monthsOptions(gradeForm.value.startAt));

function initGradeBlock() {
    if (!gradeOp.value) selectGradeOp('upgrade');
    else selectGradeOp(gradeOp.value);
}

function selectGradeOp(op) {
    gradeOp.value = op;
    gradeForm.value.gradeId = '';
    gradeAmount.value = '';
    gradeExpiredAt.value = '';

    const shop = shopData.value?.shop;
    const expiredAt = shop?.expired_at ? shop.expired_at.substring(0, 10) : null;

    if (op === 'upgrade') {
        gradeForm.value.startAt = props.today;
    } else {
        if (expiredAt) {
            const nextDay = new Date(expiredAt);
            nextDay.setDate(nextDay.getDate() + 1);
            gradeForm.value.startAt = nextDay.toISOString().substring(0, 10);
        }
    }
    gradeDetails.value = [];
    triggerGradeCalculate();
}

function onGradeSelectChange() {
    triggerGradeCalculate();
}

function onGradeStartAtChange() {
    checkGradeOverlapWarning();
    // Reset months if current selection is no longer in the recomputed options
    const opts = monthsOptions(gradeForm.value.startAt);
    const valid = opts.some(o => String(o.v) === String(gradeForm.value.months));
    if (!valid) {
        gradeForm.value.months = '';
    }
    triggerGradeCalculate();
}

function checkGradeOverlapWarning() {
    const expiredAt = shopData.value?.shop?.expired_at;
    const startAt = gradeForm.value.startAt;
    if (gradeOp.value === 'upgrade' && expiredAt && startAt && startAt < expiredAt.substring(0, 10)) {
        gradeOverlapWarning.value = `⚠ 注意：所選開始日（${startAt}）早於目前合約到期日（${expiredAt.substring(0,10)}），新合約到期日將與原合約不同，請與業務確認後再送出。`;
    } else {
        gradeOverlapWarning.value = '';
    }
}

async function triggerGradeCalculate() {
    const gradeId = gradeForm.value.gradeId;
    const startAt = gradeForm.value.startAt;
    const months  = gradeForm.value.months;

    gradeAmount.value = '';
    gradeExpiredAt.value = '';

    if (!gradeId || !startAt || months === '') {
        gradeDetails.value = [];
        return;
    }

    const selectedGrade = filteredGrades.value.find(g => String(g.id) === String(gradeId));
    if (!selectedGrade) { gradeDetails.value = []; return; }

    const unitPrice = parseInt(selectedGrade.price);
    const expiredAt = shopData.value?.shop?.expired_at;
    const currentGradePrice = shopData.value?.shop?.grade_price ?? 0;
    const isUpgradeDiff = gradeOp.value === 'upgrade' && expiredAt && startAt === expiredAt.substring(0, 10);

    try {
        const res = await http.get(props.calculateUrl, {
            params: {
                unit_price: unitPrice,
                start_at: startAt,
                total_months: parseInt(months),
                type: isUpgradeDiff ? 2 : 1,
                current_grade_price: isUpgradeDiff ? currentGradePrice : undefined,
            },
        });
        gradeAmount.value = fmt(res.data.total_price);
        gradeExpiredAt.value = res.data.expired_at?.substring(0, 10) ?? '';

        gradeDetails.value = [{
            type: isUpgradeDiff ? 2 : 1,
            grade_id: parseInt(gradeId),
            name: selectedGrade.name,
            unit_price: unitPrice,
            total_price: res.data.total_price,
            start_at: startAt,
            expired_at: res.data.expired_at,
            total_months: parseInt(months),
            quantity: 1,
            payment_type: paymentTypeFromMonths(parseInt(months)),
        }];
    } catch { /* ignore */ }
}

// ─── Addon Block ──────────────────────────────────────────────
let addonRowCount = 0;
const addonRows = ref([]);

function getAddonOptions(row) {
    const addons = shopData.value?.addons ?? [];
    const shopAddonIds = (shopData.value?.shop_addons ?? []).map(sa => sa.addon_id);
    const gradeId = shopData.value?.shop?.grade_id;
    const gradeAddonIds = ((shopData.value?.grades ?? []).find(g => g.id === gradeId)?.addons ?? []).map(a => a.id);

    // collect selected addon ids from other rows
    const selectedIds = addonRows.value
        .filter(r => r.id !== row.id && r.addonId)
        .map(r => r.addonId);

    return addons.map(a => {
        const inGrade = gradeAddonIds.includes(a.id);
        const isPurchased = shopAddonIds.includes(a.id);
        const sa = (shopData.value?.shop_addons ?? []).find(x => x.addon_id === a.id);
        let suffix = '';
        if (inGrade) suffix = '（已包含）';
        else if (isPurchased) suffix = sa?.expired_at ? `（已購買，到期 ${sa.expired_at.substring(0, 10)}）` : '（已購買）';

        const alreadyInOtherRow = selectedIds.includes(String(a.id)) && row.addonId !== String(a.id);
        let label = a.name + suffix;
        if (alreadyInOtherRow && !label.includes('已加入')) label += '（已加入）';

        return {
            id: a.id,
            price: a.price,
            name: a.name,
            type: a.type,
            inGrade,
            disabled: inGrade || alreadyInOtherRow,
            label,
        };
    });
}

function addAddonRow() {
    const id = ++addonRowCount;
    addonRows.value.push({
        id,
        addonId: '',
        qty: 1,
        startAt: props.today,
        months: '',
        monthsOptions: monthsOptions(props.today),
        isQuota: false,
        amount: '',
        detail: null,
    });
}

function removeAddonRow(row) {
    addonRows.value = addonRows.value.filter(r => r.id !== row.id);
}

function onAddonSelectChange(row) {
    const addons = shopData.value?.addons ?? [];
    const addon = addons.find(a => String(a.id) === row.addonId);
    row.isQuota = Number(addon?.type) === 2;
    triggerAddonCalculate(row);
}

function onAddonStartAtChange(row) {
    row.monthsOptions = monthsOptions(row.startAt);
    row.months = '';
    triggerAddonCalculate(row);
}

async function triggerAddonCalculate(row) {
    row.amount = '';
    row.detail = null;

    const addons = shopData.value?.addons ?? [];
    const addon = addons.find(a => String(a.id) === row.addonId);
    if (!addon || !row.startAt || row.months === '') return;

    try {
        const res = await http.get(props.calculateUrl, {
            params: {
                unit_price: parseInt(addon.price),
                start_at: row.startAt,
                total_months: parseInt(row.months),
                type: 3,
            },
        });
        const qty = row.qty || 1;
        const totalPrice = res.data.total_price * qty;
        row.amount = fmt(totalPrice);
        row.detail = {
            type: 3,
            addon_id: parseInt(row.addonId),
            name: addon.name,
            unit_price: parseInt(addon.price),
            total_price: totalPrice,
            start_at: row.startAt,
            expired_at: res.data.expired_at,
            total_months: parseInt(row.months),
            quantity: qty,
            payment_type: paymentTypeFromMonths(parseInt(row.months)),
        };
    } catch { /* ignore */ }
}

// ─── Discount Block ───────────────────────────────────────────
const discount = ref({ typeId: '', amount: 0 });
const discountInfo  = ref('');
const discountError = ref('');

function onDiscountTypeChange() {
    discount.value.amount = 0;
    discountInfo.value = '';
    discountError.value = '';
}

function onDiscountAmountInput() {
    const amount = discount.value.amount || 0;
    discountError.value = '';
    discountInfo.value = '';

    if (amount > subtotal.value) {
        discountError.value = `折抵金額（NT$${amount.toLocaleString()}）不得大於小計（NT$${subtotal.value.toLocaleString()}）`;
    } else if (amount > 0) {
        const disc = props.discounts.find(d => String(d.id) === String(discount.value.typeId));
        discountInfo.value = `套用：${disc?.name ?? ''} NT$${amount.toLocaleString()}`;
    }
}

// ─── Computed summaries ───────────────────────────────────────
const allDetails = computed(() => {
    const details = [];
    gradeDetails.value.forEach(d => details.push(d));
    addonRows.value.forEach(row => { if (row.detail) details.push(row.detail); });
    return details;
});

const subtotal = computed(() => {
    let s = 0;
    allDetails.value.forEach(d => { s += d.total_price; });
    return s;
});

const effectiveDiscount = computed(() => {
    const amt = discount.value.amount || 0;
    return (amt > 0 && amt <= subtotal.value) ? amt : 0;
});

const total = computed(() => Math.max(0, subtotal.value - effectiveDiscount.value));

// ─── Submit ───────────────────────────────────────────────────
const paymentMethod = ref(2);
let formSubmitHandler = null;

// Keys for detail hidden inputs (null/undefined entries are skipped by v-if)
const detailKeys = [
    'type', 'grade_id', 'addon_id', 'payment_type', 'quantity',
    'unit_price', 'total_price', 'name', 'start_at', 'expired_at', 'total_months',
];

function validateSubmit() {
    const discAmt = discount.value.amount || 0;
    if (discAmt > subtotal.value) {
        discountError.value = '折抵金額不得大於小計';
        return false;
    }
    if (!shopData.value?.shop?.id) {
        showFlash('error', '請先搜尋並確認商店');
        return false;
    }
    return true;
}

function onSubmit() {
    if (!validateSubmit()) return;

    // Submit the ancestor <form id="bill-form"> natively (bypasses Vue to avoid infinite loop)
    const form = document.getElementById('bill-form');
    if (form) {
        HTMLFormElement.prototype.submit.call(form);
    } else {
        showFlash('error', '表單元素不存在，請重新整理頁面');
    }
}

// ─── Lifecycle ────────────────────────────────────────────────
onMounted(() => {
    document.addEventListener('click', onDocumentClick);

    // Guard Enter-key (and any non-button) submit against validation bypass
    const form = document.getElementById('bill-form');
    if (form) {
        formSubmitHandler = (e) => {
            if (!validateSubmit()) {
                e.preventDefault();
            }
        };
        form.addEventListener('submit', formSubmitHandler);
    }
});

onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
    document.removeEventListener('click', onDocumentClick);

    const form = document.getElementById('bill-form');
    if (form && formSubmitHandler) {
        form.removeEventListener('submit', formSubmitHandler);
    }
});
</script>
