const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

// ─── Pay Modal ────────────────────────────────────────────────
const payModal   = document.getElementById('pay-modal');
const payModalNo = document.getElementById('pay-modal-no');
const payCancel  = document.getElementById('pay-cancel');
const payConfirm = document.getElementById('pay-confirm');

let activeBillId = null;

document.querySelectorAll('.pay-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        activeBillId = btn.dataset.billId;
        payModalNo.textContent = btn.dataset.billNo;
        payModal.classList.remove('hidden');
    });
});

payCancel?.addEventListener('click', () => payModal.classList.add('hidden'));
payModal?.addEventListener('click', e => { if (e.target === payModal) payModal.classList.add('hidden'); });

payConfirm?.addEventListener('click', async () => {
    if (!activeBillId) return;
    payConfirm.disabled = true;
    payConfirm.textContent = '處理中…';

    try {
        const res = await axios.post(`/bills/${activeBillId}/pay`);
        payModal.classList.add('hidden');
        window.location.reload();
    } catch (err) {
        alert(err.response?.data?.message || '付款失敗，請稍後再試');
        payConfirm.disabled = false;
        payConfirm.textContent = '確認付款';
    }
});

// ─── Writeoff Modal ───────────────────────────────────────────
const writeoffModal    = document.getElementById('writeoff-modal');
const writeoffModalNo  = document.getElementById('writeoff-modal-no');
const writeoffDetailList = document.getElementById('writeoff-detail-list');
const writeoffCancel   = document.getElementById('writeoff-cancel');
const writeoffConfirm  = document.getElementById('writeoff-confirm');

document.querySelectorAll('.writeoff-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        activeBillId = btn.dataset.billId;
        writeoffModalNo.textContent = btn.dataset.billNo;
        writeoffDetailList.innerHTML = '<p class="text-sm text-gray-400">載入中…</p>';
        writeoffModal.classList.remove('hidden');

        try {
            const res = await axios.get(`/bills/${activeBillId}/details`);
            const details = res.data.details ?? [];
            if (details.length === 0) {
                writeoffDetailList.innerHTML = '<p class="text-sm text-gray-400">無有效項目</p>';
                return;
            }
            writeoffDetailList.innerHTML = details.map(d => `
                <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" class="writeoff-detail-cb mt-0.5" value="${d.id}" checked>
                    <span class="flex-1">${d.name}</span>
                    <span class="text-gray-500 text-xs">${d.quantity} × NT$${d.unit_price.toLocaleString()}</span>
                    <span class="font-medium">NT$${d.total_price.toLocaleString()}</span>
                </label>
            `).join('');
        } catch {
            writeoffDetailList.innerHTML = '<p class="text-sm text-red-500">載入失敗</p>';
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
