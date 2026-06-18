<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import http from '@/lib/http';
import { useFlash } from '@/composables/useFlash';

defineProps({
    csrfToken: { type: String, default: '' },
});

const { showFlash } = useFlash();

// ─── Constants ────────────────────────────────────────────────
const typeLabels = { 1: '版本', 2: '升級補差額', 3: '加購功能', 4: '折抵' };
const typeBadgeClass = {
    1: 'bg-blue-100 text-blue-700',
    2: 'bg-purple-100 text-purple-700',
    3: 'bg-green-100 text-green-700',
    4: 'bg-orange-100 text-orange-700',
};

// ─── Detail modal state ───────────────────────────────────────
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailBillNo = ref('');
const detailBill = ref(null);
const detailActiveItems = ref([]);
const detailVoidItems = ref([]);
const detailError = ref(false);
let activeBillId = null;

// ─── Export modal state ───────────────────────────────────────
const exportOpen = ref(false);
const exportMsg = ref('');

// ─── Writeoff modal state ─────────────────────────────────────
const writeoffOpen = ref(false);
const writeoffLoading = ref(false);
const writeoffBillNo = ref('');
const writeoffItems = ref([]);
const writeoffChecked = ref([]);
const writeoffError = ref(false);
const writeoffSubmitting = ref(false);
let writeoffBillId = null;

// ─── Edit modal state ─────────────────────────────────────────
const editOpen = ref(false);
const editBillNo = ref('');
const editPaymentStatus = ref('');
const editPaidAt = ref('');
const editInvoiceNo = ref('');
const editSubmitting = ref(false);
let editBillId = null;

// ─── Button handler refs for cleanup ─────────────────────────
const detailHandlers = [];
const writeoffHandlers = [];
const editHandlers = [];

// ─── Detail modal ─────────────────────────────────────────────
async function openDetail(btn) {
    activeBillId = btn.dataset.billId;
    detailBillNo.value = btn.dataset.billNo;
    detailBill.value = null;
    detailActiveItems.value = [];
    detailVoidItems.value = [];
    detailError.value = false;
    detailLoading.value = true;
    detailOpen.value = true;

    try {
        const res = await http.get(`/bills/${activeBillId}/detail`);
        const { bill, details } = res.data;
        detailBill.value = bill;
        detailActiveItems.value = details.filter(d => d.is_effective !== 0);
        detailVoidItems.value = details.filter(d => d.is_effective === 0);
    } catch {
        detailError.value = true;
    } finally {
        detailLoading.value = false;
    }
}

function closeDetail() {
    detailOpen.value = false;
}

// ─── Export ───────────────────────────────────────────────────
async function doExport() {
    if (!activeBillId) { return; }
    exportMsg.value = '匯出中…';
    exportOpen.value = true;

    try {
        const res = await http.get(`/bills/${activeBillId}/quotation`, { responseType: 'blob' });
        const disposition = res.headers['content-disposition'] ?? '';
        const rfc5987 = disposition.match(/filename\*=UTF-8''([^;\n]+)/i);
        const ascii = disposition.match(/filename="([^"]+)"/i);
        const filename = rfc5987 ? decodeURIComponent(rfc5987[1]) : (ascii?.[1] ?? 'quotation.pdf');

        const url = URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(url);

        exportMsg.value = '匯出完成';
        setTimeout(() => { exportOpen.value = false; }, 1500);
    } catch {
        exportOpen.value = false;
        showFlash('error', '匯出失敗，請稍後再試');
    }
}

// ─── Writeoff modal ───────────────────────────────────────────
async function openWriteoff(btn) {
    writeoffBillId = btn.dataset.billId;
    writeoffBillNo.value = btn.dataset.billNo;
    writeoffItems.value = [];
    writeoffChecked.value = [];
    writeoffError.value = false;
    writeoffLoading.value = true;
    writeoffSubmitting.value = false;
    writeoffOpen.value = true;

    try {
        const res = await http.get(`/bills/${writeoffBillId}/detail`);
        writeoffItems.value = (res.data.details ?? []).filter(d => d.is_effective === 1 && d.type !== 4);
    } catch {
        writeoffError.value = true;
    } finally {
        writeoffLoading.value = false;
    }
}

function closeWriteoff() {
    writeoffOpen.value = false;
}

function toggleWriteoffItem(id) {
    const idx = writeoffChecked.value.indexOf(id);
    if (idx === -1) {
        writeoffChecked.value.push(id);
    } else {
        writeoffChecked.value.splice(idx, 1);
    }
}

async function confirmWriteoff() {
    if (writeoffChecked.value.length === 0) {
        showFlash('error', '請至少勾選一個項目');
        return;
    }
    writeoffSubmitting.value = true;
    try {
        await http.post(`/bills/${writeoffBillId}/writeoff`, { detail_ids: writeoffChecked.value });
        writeoffOpen.value = false;
        window.location.reload();
    } catch (err) {
        showFlash('error', err.response?.data?.message || '銷帳失敗，請稍後再試');
        writeoffSubmitting.value = false;
    }
}

// ─── Edit modal ───────────────────────────────────────────────
function openEdit(btn) {
    editBillId = btn.dataset.billId;
    editBillNo.value = btn.dataset.billNo;
    editPaymentStatus.value = btn.dataset.paymentStatus ?? '';
    editPaidAt.value = btn.dataset.paidAt ?? '';
    editInvoiceNo.value = btn.dataset.invoiceNo ?? '';
    editSubmitting.value = false;
    editOpen.value = true;
}

function closeEdit() {
    editOpen.value = false;
}

async function confirmEdit() {
    if (!editBillId) { return; }
    editSubmitting.value = true;
    try {
        await http.patch(`/bills/${editBillId}`, {
            payment_status: parseInt(editPaymentStatus.value),
            paid_at: editPaidAt.value || null,
            invoice_no: editInvoiceNo.value || null,
        });
        editOpen.value = false;
        window.location.reload();
    } catch (err) {
        showFlash('error', err.response?.data?.message || '儲存失敗，請稍後再試');
        editSubmitting.value = false;
    }
}

// ─── Lifecycle ────────────────────────────────────────────────
onMounted(() => {
    document.querySelectorAll('.detail-btn').forEach(btn => {
        const handler = () => openDetail(btn);
        btn.addEventListener('click', handler);
        detailHandlers.push({ btn, handler });
    });

    document.querySelectorAll('.writeoff-btn').forEach(btn => {
        const handler = () => openWriteoff(btn);
        btn.addEventListener('click', handler);
        writeoffHandlers.push({ btn, handler });
    });

    document.querySelectorAll('.edit-btn').forEach(btn => {
        const handler = () => openEdit(btn);
        btn.addEventListener('click', handler);
        editHandlers.push({ btn, handler });
    });
});

onBeforeUnmount(() => {
    detailHandlers.forEach(({ btn, handler }) => btn.removeEventListener('click', handler));
    writeoffHandlers.forEach(({ btn, handler }) => btn.removeEventListener('click', handler));
    editHandlers.forEach(({ btn, handler }) => btn.removeEventListener('click', handler));
});
</script>

<template>
  <!-- Detail Modal -->
  <div v-if="detailOpen" class="modal-overlay" @click.self="closeDetail">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 max-h-[90vh] flex flex-col">
      <div class="flex items-start justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-800">帳單明細</h3>
          <p class="text-xs text-gray-500 font-mono mt-0.5">{{ detailBillNo }}</p>
        </div>
        <button class="text-gray-400 hover:text-gray-600 text-xl leading-none" @click="closeDetail">&times;</button>
      </div>

      <div v-if="detailBill" class="text-sm text-gray-600 grid grid-cols-2 gap-x-6 gap-y-1 mb-4">
        <div><span class="text-gray-400">商店</span>：{{ detailBill.shop_name }}</div>
        <div><span class="text-gray-400">建立人</span>：{{ detailBill.creator_name }}</div>
        <div>
          <span class="text-gray-400">狀態</span>：
          <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="detailBill.status_class">
            {{ detailBill.status_label }}
          </span>
        </div>
      </div>

      <hr class="mb-4">

      <div class="overflow-auto flex-1">
        <table class="table">
          <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              <th class="px-3 py-2">項目名稱</th>
              <th class="px-3 py-2">類型</th>
              <th class="px-3 py-2 text-right">總價</th>
              <th class="px-3 py-2">起始日</th>
              <th class="px-3 py-2">到期日</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="detailLoading">
              <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">載入中…</td>
            </tr>
            <tr v-else-if="detailError">
              <td colspan="5" class="px-3 py-4 text-center text-red-500 text-sm">載入失敗</td>
            </tr>
            <tr v-else-if="detailActiveItems.length === 0">
              <td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">無明細</td>
            </tr>
            <tr v-for="d in detailActiveItems" v-else :key="d.id" class="text-gray-700">
              <td class="px-3 py-2">{{ d.name }}</td>
              <td class="px-3 py-2">
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                      :class="typeBadgeClass[d.type] ?? 'bg-gray-100 text-gray-500'">
                  {{ typeLabels[d.type] ?? '—' }}
                </span>
              </td>
              <td class="px-3 py-2 text-right font-medium">NT${{ d.total_price.toLocaleString() }}</td>
              <td class="px-3 py-2 text-xs">{{ d.type === 4 ? '' : (d.start_at ?? '—') }}</td>
              <td class="px-3 py-2 text-xs">{{ d.type === 4 ? '' : (d.expired_at ?? '—') }}</td>
            </tr>
          </tbody>
        </table>

        <!-- Void items section -->
        <div v-if="!detailLoading && !detailError && detailVoidItems.length > 0" class="mt-6">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">作廢項目</span>
            <div class="flex-1 border-t border-dashed border-gray-200"></div>
          </div>
          <table class="table">
            <thead class="text-gray-400 text-xs uppercase">
              <tr>
                <th class="px-3 py-2">項目名稱</th>
                <th class="px-3 py-2">類型</th>
                <th class="px-3 py-2 text-right">總價</th>
                <th class="px-3 py-2">起始日</th>
                <th class="px-3 py-2">到期日</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="d in detailVoidItems" :key="d.id" class="text-gray-400">
                <td class="px-3 py-2"><s>{{ d.name }}</s></td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-400">
                    {{ typeLabels[d.type] ?? '—' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right font-medium">NT${{ d.total_price.toLocaleString() }}</td>
                <td class="px-3 py-2 text-xs">{{ d.type === 4 ? '' : (d.start_at ?? '—') }}</td>
                <td class="px-3 py-2 text-xs">{{ d.type === 4 ? '' : (d.expired_at ?? '—') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <hr class="mt-4 mb-3">

      <div class="flex items-end justify-between gap-4">
        <button v-if="detailBill && detailBill.payment_status === 1"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded text-sm font-medium bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition"
                @click="doExport">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
          </svg>
          匯出報價單
        </button>
        <div v-if="detailBill" class="text-sm text-right space-y-1 ml-auto">
          <div class="text-gray-500">小計：NT${{ (detailBill.total_grade + detailBill.total_addons).toLocaleString() }}</div>
          <div v-if="detailBill.discount_amount" class="text-gray-500">折抵：－NT${{ detailBill.discount_amount.toLocaleString() }}</div>
          <div class="font-semibold text-gray-800 text-base">總金額：NT${{ detailBill.total.toLocaleString() }}</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Progress Modal -->
  <div v-if="exportOpen" class="modal-overlay">
    <div class="bg-white rounded-xl shadow-xl px-10 py-8 flex flex-col items-center gap-3 min-w-48">
      <p class="text-sm font-medium text-gray-700">{{ exportMsg }}</p>
    </div>
  </div>

  <!-- Writeoff Modal -->
  <div v-if="writeoffOpen" class="modal-overlay" @click.self="closeWriteoff">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-6 max-h-[90vh] flex flex-col">
      <h3 class="text-lg font-semibold text-gray-800 mb-1">銷帳</h3>
      <p class="text-xs text-gray-500 font-mono mb-4">{{ writeoffBillNo }}</p>
      <hr class="mb-4">

      <div class="overflow-auto flex-1">
        <table class="table">
          <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              <th class="px-3 py-2 w-8"></th>
              <th class="px-3 py-2">項目名稱</th>
              <th class="px-3 py-2">類型</th>
              <th class="px-3 py-2 text-right">總價</th>
              <th class="px-3 py-2">起始日</th>
              <th class="px-3 py-2">到期日</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-if="writeoffLoading">
              <td colspan="6" class="px-3 py-4 text-center text-gray-400 text-sm">載入中…</td>
            </tr>
            <tr v-else-if="writeoffError">
              <td colspan="6" class="px-3 py-4 text-center text-red-500 text-sm">載入失敗</td>
            </tr>
            <tr v-else-if="writeoffItems.length === 0">
              <td colspan="6" class="px-3 py-4 text-center text-gray-400 text-sm">無有效項目</td>
            </tr>
            <template v-else>
              <tr v-for="d in writeoffItems" :key="d.id"
                  class="text-gray-700 hover:bg-gray-50 cursor-pointer"
                  @click="toggleWriteoffItem(d.id)">
                <td class="px-3 py-2">
                  <input type="checkbox"
                         :value="d.id"
                         :checked="writeoffChecked.includes(d.id)"
                         @click.stop="toggleWriteoffItem(d.id)">
                </td>
                <td class="px-3 py-2">{{ d.name }}</td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                        :class="typeBadgeClass[d.type] ?? 'bg-gray-100 text-gray-500'">
                    {{ typeLabels[d.type] ?? '—' }}
                  </span>
                </td>
                <td class="px-3 py-2 text-right font-medium">NT${{ d.total_price.toLocaleString() }}</td>
                <td class="px-3 py-2 text-xs">{{ d.start_at ?? '—' }}</td>
                <td class="px-3 py-2 text-xs">{{ d.expired_at ?? '—' }}</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <hr class="mt-4 mb-4">
      <div class="modal-actions">
        <button class="btn-cancel" @click="closeWriteoff">取消</button>
        <button class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors disabled:opacity-50"
                :disabled="writeoffSubmitting"
                data-writeoff-confirm
                @click="confirmWriteoff">
          {{ writeoffSubmitting ? '處理中…' : '進行銷帳' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div v-if="editOpen" class="modal-overlay" @click.self="closeEdit">
    <div class="modal-panel">
      <h3 class="text-lg font-semibold text-gray-800 mb-1">編輯帳務</h3>
      <p class="text-xs text-gray-500 font-mono mb-4">{{ editBillNo }}</p>
      <div class="space-y-4">
        <div class="flex flex-col gap-1">
          <label class="text-xs text-gray-500">付款狀態</label>
          <select v-model="editPaymentStatus"
                  class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <option value="1">待審核</option>
            <option value="2">待付款</option>
            <option value="3">已付款</option>
            <option value="4">已失效</option>
          </select>
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs text-gray-500">付款日期</label>
          <input v-model="editPaidAt"
                 type="date"
                 class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs text-gray-500">發票號碼</label>
          <input v-model="editInvoiceNo"
                 type="text"
                 maxlength="100"
                 class="border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
      </div>
      <div class="modal-actions mt-6">
        <button class="btn-cancel" @click="closeEdit">取消</button>
        <button class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:opacity-50"
                :disabled="editSubmitting"
                data-edit-confirm
                @click="confirmEdit">
          {{ editSubmitting ? '儲存中…' : '儲存' }}
        </button>
      </div>
    </div>
  </div>
</template>
