import { autoDismissFlashes } from '../utils/flash.js';

document.addEventListener('DOMContentLoaded', () => { autoDismissFlashes(); });

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ─── Shared state ─────────────────────────────────────────────
let activeBillId = null;

// ─── Detail Modal ─────────────────────────────────────────────
const detailModal            = document.getElementById('detail-modal');
const detailModalNo          = document.getElementById('detail-modal-no');
const detailModalMeta        = document.getElementById('detail-modal-meta');
const detailModalTbody       = document.getElementById('detail-modal-tbody');
const detailModalTotals      = document.getElementById('detail-modal-totals');
const detailModalVoidSection = document.getElementById('detail-modal-void-section');
const detailModalVoidTbody   = document.getElementById('detail-modal-void-tbody');
const detailClose            = document.getElementById('detail-close');

const typeLabels = { 1: '版本', 2: '升級補差額', 3: '加購功能', 4: '折抵' };
const typeBadgeClass = {
    1: 'bg-blue-100 text-blue-700',
    2: 'bg-purple-100 text-purple-700',
    3: 'bg-green-100 text-green-700',
    4: 'bg-orange-100 text-orange-700',
};

document.querySelectorAll('.detail-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        activeBillId = btn.dataset.billId;
        detailModalNo.textContent = btn.dataset.billNo;
        detailModalMeta.innerHTML = '';
        detailModalTbody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">載入中…</td></tr>';
        detailModalTotals.innerHTML = '';
        detailModalVoidSection.classList.add('hidden');
        document.getElementById('detail-export-btn').style.display = 'none';
        detailModal.classList.remove('hidden');

        try {
            const res = await axios.get(`/bills/${activeBillId}/detail`);
            const { bill, details } = res.data;

            document.getElementById('detail-export-btn').style.display = bill.payment_status === 1 ? 'inline-flex' : 'none';

            detailModalMeta.innerHTML = `
                <div><span class="text-gray-400">商店</span>：${bill.shop_name}</div>
                <div><span class="text-gray-400">建立人</span>：${bill.creator_name}</div>
                <div><span class="text-gray-400">狀態</span>：<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${bill.status_class}">${bill.status_label}</span></div>
            `;

            const buildDetailRow = (d, voided = false) => {
                const textClass = voided ? 'text-gray-400' : 'text-gray-700';
                const nameCell  = voided ? `<s>${d.name}</s>` : d.name;
                const badgeClass = voided ? 'bg-gray-100 text-gray-400' : (typeBadgeClass[d.type] ?? 'bg-gray-100 text-gray-500');
                return `
                    <tr class="${textClass}">
                        <td class="px-3 py-2">${nameCell}</td>
                        <td class="px-3 py-2"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${badgeClass}">${typeLabels[d.type] ?? '—'}</span></td>
                        <td class="px-3 py-2 text-right font-medium">NT$${d.total_price.toLocaleString()}</td>
                        <td class="px-3 py-2 text-xs">${d.type === 4 ? '' : (d.start_at ?? '—')}</td>
                        <td class="px-3 py-2 text-xs">${d.type === 4 ? '' : (d.expired_at ?? '—')}</td>
                    </tr>
                `;
            };

            const activeItems = details.filter(d => d.is_effective !== 0);
            const voidItems   = details.filter(d => d.is_effective === 0);

            if (activeItems.length === 0) {
                detailModalTbody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-400 text-sm">無明細</td></tr>';
            } else {
                detailModalTbody.innerHTML = activeItems.map(d => buildDetailRow(d)).join('');
            }

            if (voidItems.length > 0) {
                detailModalVoidTbody.innerHTML = voidItems.map(d => buildDetailRow(d, true)).join('');
                detailModalVoidSection.classList.remove('hidden');
            } else {
                detailModalVoidSection.classList.add('hidden');
            }

            const subtotal = bill.total_grade + bill.total_addons;
            detailModalTotals.innerHTML = `
                <div class="text-gray-500">小計：NT$${subtotal.toLocaleString()}</div>
                ${bill.discount_amount ? `<div class="text-gray-500">折抵：－NT$${bill.discount_amount.toLocaleString()}</div>` : ''}
                <div class="font-semibold text-gray-800 text-base">總金額：NT$${bill.total.toLocaleString()}</div>
            `;
        } catch {
            detailModalTbody.innerHTML = '<tr><td colspan="5" class="px-3 py-4 text-center text-red-500 text-sm">載入失敗</td></tr>';
        }
    });
});

detailClose?.addEventListener('click', () => detailModal.classList.add('hidden'));
detailModal?.addEventListener('click', e => { if (e.target === detailModal) detailModal.classList.add('hidden'); });

document.getElementById('detail-export-btn')?.addEventListener('click', () => {
    if (activeBillId) {
        window.open(`/bills/${activeBillId}/quotation`, '_blank');
    }
});

// ─── Writeoff Modal ───────────────────────────────────────────
const writeoffModal      = document.getElementById('writeoff-modal');
const writeoffModalNo    = document.getElementById('writeoff-modal-no');
const writeoffDetailList = document.getElementById('writeoff-detail-list');
const writeoffCancel     = document.getElementById('writeoff-cancel');
const writeoffConfirm    = document.getElementById('writeoff-confirm');

document.querySelectorAll('.writeoff-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        activeBillId = btn.dataset.billId;
        writeoffModalNo.textContent = btn.dataset.billNo;
        writeoffDetailList.innerHTML = '<p class="text-sm text-gray-400">載入中…</p>';
        writeoffModal.classList.remove('hidden');

        try {
            const res = await axios.get(`/bills/${activeBillId}/detail`);
            const details = (res.data.details ?? []).filter(d => d.is_effective === 1 && d.type !== 4);
            if (details.length === 0) {
                writeoffDetailList.innerHTML = '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-400 text-sm">無有效項目</td></tr>';
                return;
            }
            writeoffDetailList.innerHTML = details.map(d => `
                <tr class="text-gray-700 hover:bg-gray-50 cursor-pointer" onclick="this.querySelector('input').click()">
                    <td class="px-3 py-2"><input type="checkbox" class="writeoff-detail-cb" value="${d.id}" onclick="event.stopPropagation()"></td>
                    <td class="px-3 py-2">${d.name}</td>
                    <td class="px-3 py-2"><span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium ${typeBadgeClass[d.type] ?? 'bg-gray-100 text-gray-500'}">${typeLabels[d.type] ?? '—'}</span></td>
                    <td class="px-3 py-2 text-right font-medium">NT$${d.total_price.toLocaleString()}</td>
                    <td class="px-3 py-2 text-xs">${d.start_at ?? '—'}</td>
                    <td class="px-3 py-2 text-xs">${d.expired_at ?? '—'}</td>
                </tr>
            `).join('');
        } catch {
            writeoffDetailList.innerHTML = '<tr><td colspan="6" class="px-3 py-4 text-center text-red-500 text-sm">載入失敗</td></tr>';
        }
    });
});

writeoffCancel?.addEventListener('click', () => writeoffModal.classList.add('hidden'));
writeoffModal?.addEventListener('click', e => { if (e.target === writeoffModal) writeoffModal.classList.add('hidden'); });

writeoffConfirm?.addEventListener('click', async () => {
    const checked = [...document.querySelectorAll('.writeoff-detail-cb:checked')].map(cb => parseInt(cb.value));
    if (checked.length === 0) { alert('請至少勾選一個項目'); return; }

    writeoffConfirm.disabled = true;
    writeoffConfirm.textContent = '處理中…';

    try {
        await axios.post(`/bills/${activeBillId}/writeoff`, { detail_ids: checked });
        writeoffModal.classList.add('hidden');
        window.location.reload();
    } catch (err) {
        alert(err.response?.data?.message || '銷帳失敗，請稍後再試');
        writeoffConfirm.disabled = false;
        writeoffConfirm.textContent = '進行銷帳';
    }
});

// ─── Edit Modal ───────────────────────────────────────────────
const editModal         = document.getElementById('edit-modal');
const editModalNo       = document.getElementById('edit-modal-no');
const editPaymentStatus = document.getElementById('edit-payment-status');
const editPaidAt        = document.getElementById('edit-paid-at');
const editInvoiceNo     = document.getElementById('edit-invoice-no');
const editCancel        = document.getElementById('edit-cancel');
const editConfirm       = document.getElementById('edit-confirm');

document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        activeBillId = btn.dataset.billId;
        editModalNo.textContent = btn.dataset.billNo;
        editPaymentStatus.value = btn.dataset.paymentStatus ?? '';
        editPaidAt.value = btn.dataset.paidAt ?? '';
        editInvoiceNo.value = btn.dataset.invoiceNo ?? '';
        editModal.classList.remove('hidden');
    });
});

editCancel?.addEventListener('click', () => editModal.classList.add('hidden'));
editModal?.addEventListener('click', e => { if (e.target === editModal) editModal.classList.add('hidden'); });

editConfirm?.addEventListener('click', async () => {
    if (!activeBillId) return;
    editConfirm.disabled = true;
    editConfirm.textContent = '儲存中…';

    try {
        await axios.patch(`/bills/${activeBillId}`, {
            payment_status: parseInt(editPaymentStatus.value),
            paid_at:    editPaidAt.value || null,
            invoice_no: editInvoiceNo.value || null,
        });
        editModal.classList.add('hidden');
        window.location.reload();
    } catch (err) {
        alert(err.response?.data?.message || '儲存失敗，請稍後再試');
        editConfirm.disabled = false;
        editConfirm.textContent = '儲存';
    }
});
