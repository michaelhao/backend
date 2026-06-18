<script setup>
import { ref, onMounted } from 'vue';
import http from '@/lib/http';
import { maskString } from '@/shops/maskString';

const props = defineProps({
    certRoute: { type: String, required: true },
    adminEmailError: { type: Boolean, default: false },
});

// Cert modal state
const modalOpen = ref(false);
const businessNumber = ref('');
const inputError = ref(false);
const resultState = ref(null); // null | 'success' | 'error'
const resultCompanyName = ref('');
const submitting = ref(false);
const certDone = ref(false);

onMounted(() => {
    // Wire email toggle
    const emailMasked = document.getElementById('admin-email-masked');
    const emailInput = document.getElementById('admin-email-input');
    const emailToggle = document.getElementById('admin-email-toggle');

    if (emailToggle) {
        emailToggle.addEventListener('click', () => {
            if (emailInput.classList.contains('hidden')) {
                // Switch to editable
                emailMasked.classList.add('hidden');
                emailInput.classList.remove('hidden');
                emailToggle.textContent = '取消';
                emailInput.focus();
            } else {
                // Switch to masked
                emailMasked.classList.remove('hidden');
                emailInput.classList.add('hidden');
                emailToggle.textContent = '修改';
            }
        });

        if (props.adminEmailError) {
            emailToggle.click();
        }
    }

    // Wire open-cert-modal button
    const openCertBtn = document.getElementById('open-cert-modal');
    if (openCertBtn) {
        openCertBtn.addEventListener('click', () => {
            businessNumber.value = '';
            inputError.value = false;
            resultState.value = null;
            certDone.value = false;
            submitting.value = false;
            modalOpen.value = true;
        });
    }
});

function onBusinessNumberInput(e) {
    businessNumber.value = e.target.value.replace(/\D/g, '');
    e.target.value = businessNumber.value;
}

async function submitCert() {
    const bn = businessNumber.value.trim();
    if (!/^\d{8}$/.test(bn)) {
        inputError.value = true;
        return;
    }
    inputError.value = false;
    submitting.value = true;
    resultState.value = null;

    try {
        const res = await http.post(props.certRoute, { business_number: bn });
        const data = res.data;

        if (data.success) {
            document.getElementById('business-number-hidden').value = bn;
            document.getElementById('business-number-display').value = maskString(bn);
            document.getElementById('company-name-hidden').value = data.company_name;
            document.getElementById('company-name-display').value = data.company_name;

            resultCompanyName.value = data.company_name;
            resultState.value = 'success';
            certDone.value = true;
        } else {
            resultState.value = 'error';
        }
    } catch {
        resultState.value = 'error';
    } finally {
        submitting.value = false;
    }
}

function closeModal() {
    modalOpen.value = false;
}
</script>

<template>
  <div v-if="modalOpen" class="modal-overlay" @click.self="closeModal">
    <div class="modal-panel">
      <h3 class="text-lg font-semibold text-gray-800 mb-4">商家認證</h3>

      <div class="mb-4">
        <label for="cert-business-number" class="form-label">統一編號（8 位數字）</label>
        <input
          id="cert-business-number"
          :value="businessNumber"
          maxlength="8"
          inputmode="numeric"
          pattern="\d{8}"
          placeholder="請輸入統一編號"
          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono focus:border-blue-500 focus:outline-none"
          @input="onBusinessNumberInput"
        >
        <p v-if="inputError" class="mt-1 text-xs text-red-600">請輸入 8 位數字</p>
      </div>

      <div v-if="resultState === 'success'"
           class="mb-4 rounded-md p-3 text-sm bg-green-50 text-green-700">
        <strong>認證成功</strong><br>
        公司名稱：{{ resultCompanyName }}<br>
        <span class="text-xs text-green-600 mt-1 block">請儲存商店資料以完成認證流程</span>
      </div>

      <div v-if="resultState === 'error'"
           class="mb-4 rounded-md p-3 text-sm bg-red-50 text-red-700">
        認證失敗，請確認統一編號是否正確
      </div>

      <div class="modal-actions">
        <button type="button"
                class="bg-gray-100 text-gray-700 px-4 py-2 rounded-md text-sm font-medium hover:bg-gray-200 transition-colors"
                @click="closeModal">
          取消
        </button>
        <button v-if="!certDone"
                type="button"
                :disabled="submitting"
                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50"
                @click="submitCert">
          {{ submitting ? '認證中...' : '認證' }}
        </button>
        <button v-else
                type="button"
                class="bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium hover:bg-blue-700 transition-colors"
                @click="closeModal">
          完成
        </button>
      </div>
    </div>
  </div>
</template>
