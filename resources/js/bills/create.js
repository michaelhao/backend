// Bills create wizard — step-by-step, single page
const cfg = window.billConfig || {};
let shopData = null;   // Full shop info from AJAX
let gradeDetails = []; // Will hold the grade detail if configured
let addonRows   = [];  // Will hold addon detail rows

// ─── Utility ─────────────────────────────────────────────────
function fmt(n) { return 'NT$' + Number(n).toLocaleString(); }

function show(id) { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id) { document.getElementById(id)?.classList.add('hidden'); }

function monthsOptions(startDate) {
    const d = startDate ? new Date(startDate) : null;
    const isFirst = d && d.getDate() === 1;
    const isLastDay = d && (() => {
        const last = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        return d.getDate() === last.getDate();
    })();

    const opts = [];
    if (d && !isFirst && !isLastDay) opts.push({ v: 0, l: '月底' });
    for (let m = 1; m <= 36; m++) {
        let label = `${m} 個月`;
        if (m === 6)  label = '6 個月（半年）';
        if (m === 12) label = '12 個月（年繳）';
        if (m === 24) label = '24 個月（2 年繳）';
        if (m === 36) label = '36 個月（3 年繳）';
        opts.push({ v: m, l: label });
    }
    return opts;
}

function buildMonthsSelect(selectEl, startDate) {
    const current = selectEl.value;
    selectEl.innerHTML = '<option value="">— 請選擇 —</option>';
    monthsOptions(startDate).forEach(o => {
        const opt = document.createElement('option');
        opt.value = o.v;
        opt.textContent = o.l;
        if (String(o.v) === current) opt.selected = true;
        selectEl.appendChild(opt);
    });
}

// ─── Step 1: Search ───────────────────────────────────────────
const keywordInput   = document.getElementById('shop-keyword');
const searchBtn      = document.getElementById('shop-search-btn');
const dropdown       = document.getElementById('shop-dropdown');
const selectedInfo   = document.getElementById('shop-selected-info');
const selectedLabel  = document.getElementById('shop-selected-label');
const confirmBtn     = document.getElementById('shop-confirm-btn');

let selectedShopId = null;
let searchTimeout = null;

async function doSearch(kw) {
    if (!kw.trim()) { dropdown.classList.add('hidden'); return; }
    try {
        const res = await axios.get(cfg.shopSearchUrl, { params: { keyword: kw } });
        const shops = res.data.shops ?? [];
        if (!shops.length) {
            dropdown.innerHTML = '<div class="px-4 py-2 text-sm text-gray-400">找不到符合的商店</div>';
        } else {
            dropdown.innerHTML = shops.map(s =>
                `<div class="px-4 py-2 text-sm text-gray-700 hover:bg-blue-50 cursor-pointer shop-option"
                      data-id="${s.id}" data-label="${s.label}">${s.label}</div>`
            ).join('');
            dropdown.querySelectorAll('.shop-option').forEach(el => {
                el.addEventListener('click', () => {
                    selectedShopId = parseInt(el.dataset.id);
                    selectedLabel.textContent = el.dataset.label;
                    selectedInfo.classList.remove('hidden');
                    dropdown.classList.add('hidden');
                });
            });
        }
        dropdown.classList.remove('hidden');
    } catch { dropdown.classList.add('hidden'); }
}

keywordInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => doSearch(keywordInput.value), 300);
});
searchBtn.addEventListener('click', () => doSearch(keywordInput.value));
document.addEventListener('click', e => {
    if (!dropdown.contains(e.target) && e.target !== keywordInput && e.target !== searchBtn) {
        dropdown.classList.add('hidden');
    }
});

// ─── Step 1 → Step 2 → Step 3 ────────────────────────────────
confirmBtn.addEventListener('click', async () => {
    if (!selectedShopId) return;
    hide('step-1');
    show('step-2');

    try {
        const res = await axios.get(cfg.shopInfoUrl, { params: { shop_id: selectedShopId } });
        shopData = res.data;
        renderStep3(shopData);
        hide('step-2');
        show('step-3');
    } catch (err) {
        hide('step-2');
        show('step-1');
        alert(err.response?.data?.error || '無法載入商店資訊');
    }
});

function renderStep3(data) {
    const shop = data.shop;
    document.getElementById('info-shop-id').textContent = shop.id;
    document.getElementById('info-shop-name').textContent = shop.name;
    document.getElementById('info-shop-grade').textContent = shop.grade || '—';
    document.getElementById('info-shop-status').textContent = shop.status;
    document.getElementById('info-shop-expired').textContent = shop.expired_at ? shop.expired_at.substring(0, 10) : '—';

    const warnEl = document.getElementById('pending-bill-warning');
    if (data.pending_bill_count > 0) {
        warnEl.textContent = `⚠ 此商店有 ${data.pending_bill_count} 張待處理帳單，建議先完成付款或銷帳後再建立新帳單，以避免升級殘值計算錯誤。`;
        warnEl.classList.remove('hidden');
    } else {
        warnEl.classList.add('hidden');
    }
}

// ─── Step 3: Toggle grade/addon ──────────────────────────────
const gradeBtn = document.getElementById('toggle-grade-btn');
const addonBtn = document.getElementById('toggle-addon-btn');
let gradeEnabled = false;
let addonEnabled = false;

function updateToggleBtn(btn, active) {
    if (active) {
        btn.classList.remove('border-gray-300', 'text-gray-600');
        btn.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        btn.innerHTML = btn.textContent.trim().replace(/[✓]/g, '') + ' <span class="text-blue-500">✓</span>';
    } else {
        btn.classList.add('border-gray-300', 'text-gray-600');
        btn.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
        btn.textContent = btn.textContent.replace(' ✓', '').replace(/[✓]/g, '').trim();
    }
}

gradeBtn.addEventListener('click', () => {
    gradeEnabled = !gradeEnabled;
    updateToggleBtn(gradeBtn, gradeEnabled);
    if (gradeEnabled) {
        show('step-4');
        show('grade-block');
        initGradeBlock();
    } else {
        hide('grade-block');
    }
    updateDiscountAndSummary();
    updateSubmitBlock();
});

addonBtn.addEventListener('click', () => {
    addonEnabled = !addonEnabled;
    updateToggleBtn(addonBtn, addonEnabled);
    if (addonEnabled) {
        show('step-4');
        show('addon-block');
        if (document.getElementById('addon-rows').children.length === 0) addAddonRow();
    } else {
        hide('addon-block');
    }
    updateDiscountAndSummary();
    updateSubmitBlock();
});

// ─── Grade Block ──────────────────────────────────────────────
let currentGradeOp = null;

function initGradeBlock() {
    // Set date constraints based on op (default: upgrade → today)
    if (!currentGradeOp) selectGradeOp('upgrade');
}

function selectGradeOp(op) {
    currentGradeOp = op;
    document.querySelectorAll('.grade-op-btn').forEach(b => {
        const active = b.dataset.gradeOp === op;
        b.classList.toggle('border-blue-500', active);
        b.classList.toggle('text-blue-600', active);
        b.classList.toggle('bg-blue-50', active);
        b.classList.toggle('border-gray-300', !active);
        b.classList.toggle('text-gray-600', !active);
    });

    const startAtInput = document.getElementById('grade-start-at');
    const shop = shopData?.shop;
    const expiredAt = shop?.expired_at ? shop.expired_at.substring(0, 10) : null;

    if (op === 'upgrade') {
        startAtInput.min = cfg.today;
        startAtInput.readOnly = false;
        startAtInput.value = cfg.today;
    } else {
        // renew / downgrade: lock to expired_at + 1 day
        if (expiredAt) {
            const nextDay = new Date(expiredAt);
            nextDay.setDate(nextDay.getDate() + 1);
            const nd = nextDay.toISOString().substring(0, 10);
            startAtInput.value = nd;
            startAtInput.readOnly = true;
        }
    }

    populateGradeSelect(op);
    buildMonthsSelect(document.getElementById('grade-months'), startAtInput.value);
    triggerGradeCalculate();
}

document.querySelectorAll('.grade-op-btn').forEach(btn => {
    btn.addEventListener('click', () => selectGradeOp(btn.dataset.gradeOp));
});

function populateGradeSelect(op) {
    const select = document.getElementById('grade-select');
    const grades = shopData?.grades ?? [];
    const currentWeight = shopData?.shop?.grade_weight ?? 0;
    select.innerHTML = '<option value="">— 請選擇版本 —</option>';

    grades.forEach(g => {
        if (op === 'upgrade' && g.weight <= currentWeight) return;
        if (op === 'downgrade' && g.weight >= currentWeight) return;
        if (op === 'renew' && g.weight !== currentWeight) return;
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.dataset.price = g.price;
        opt.dataset.weight = g.weight;
        opt.dataset.name = g.name;
        opt.textContent = `${g.name}（NT$${Number(g.price).toLocaleString()}/月）`;
        select.appendChild(opt);
    });
}

const gradeSelect  = document.getElementById('grade-select');
const gradeStartAt = document.getElementById('grade-start-at');
const gradeMonths  = document.getElementById('grade-months');

gradeSelect.addEventListener('change', () => triggerGradeCalculate());

gradeStartAt.addEventListener('change', () => {
    buildMonthsSelect(gradeMonths, gradeStartAt.value);
    checkGradeOverlapWarning();
    triggerGradeCalculate();
});

gradeMonths.addEventListener('change', () => triggerGradeCalculate());

function checkGradeOverlapWarning() {
    const warnEl = document.getElementById('grade-overlap-warning');
    const expiredAt = shopData?.shop?.expired_at;
    const startAt = gradeStartAt.value;
    if (currentGradeOp === 'upgrade' && expiredAt && startAt && startAt < expiredAt.substring(0, 10)) {
        warnEl.textContent = `⚠ 注意：所選開始日（${startAt}）早於目前合約到期日（${expiredAt.substring(0,10)}），新合約到期日將與原合約不同，請與業務確認後再送出。`;
        warnEl.classList.remove('hidden');
    } else {
        warnEl.classList.add('hidden');
    }
}

async function triggerGradeCalculate() {
    const selectedOpt = gradeSelect.selectedOptions[0];
    const startAt = gradeStartAt.value;
    const months = gradeMonths.value;

    document.getElementById('grade-amount').value = '';
    document.getElementById('grade-expired-at').value = '';

    if (!selectedOpt?.dataset?.price || !startAt || months === '') return;

    const unitPrice = parseInt(selectedOpt.dataset.price);
    const expiredAt = shopData?.shop?.expired_at;
    const currentGradePrice = shopData?.shop?.grade_price ?? 0;
    const isUpgradeDiff = currentGradeOp === 'upgrade' && expiredAt && startAt === expiredAt.substring(0, 10);

    try {
        const res = await axios.get(cfg.calculateUrl, {
            params: {
                unit_price: unitPrice,
                start_at: startAt,
                total_months: parseInt(months),
                type: isUpgradeDiff ? 2 : 1,
                current_grade_price: isUpgradeDiff ? currentGradePrice : undefined,
            },
        });
        document.getElementById('grade-amount').value = fmt(res.data.total_price);
        document.getElementById('grade-expired-at').value = res.data.expired_at?.substring(0, 10);

        // Build grade detail for summary
        gradeDetails = [{
            type: isUpgradeDiff ? 2 : 1,
            name: selectedOpt.dataset.name,
            unit_price: unitPrice,
            total_price: res.data.total_price,
            start_at: startAt,
            expired_at: res.data.expired_at,
            total_months: parseInt(months),
            quantity: 1,
            payment_type: paymentTypeFromMonths(parseInt(months)),
        }];
        updateOrderSummary();
        updateDiscountAndSummary();
        updateSubmitBlock();
    } catch { /* ignore */ }
}

function paymentTypeFromMonths(m) {
    if (m === 1) return 1;
    if (m === 3) return 2;
    if (m === 12 || m === 24 || m === 36) return 3;
    return null;
}

// ─── Addon Block ──────────────────────────────────────────────
const addonRowsEl = document.getElementById('addon-rows');
let addonRowCount = 0;

document.getElementById('add-addon-row-btn').addEventListener('click', addAddonRow);

function addAddonRow() {
    const id = ++addonRowCount;
    const addons = shopData?.addons ?? [];
    const shopAddonIds = (shopData?.shop_addons ?? []).map(sa => sa.addon_id);
    const gradeAddonIds = ((shopData?.grades ?? []).find(g => g.id === shopData?.shop?.grade_id)?.addons ?? []).map(a => a.id);

    const row = document.createElement('div');
    row.className = 'addon-row border border-gray-100 rounded-lg p-3 bg-gray-50';
    row.dataset.rowId = id;

    const addonOpts = addons.map(a => {
        const inGrade = gradeAddonIds.includes(a.id);
        const isPurchased = shopAddonIds.includes(a.id);
        const sa = (shopData?.shop_addons ?? []).find(x => x.addon_id === a.id);
        let suffix = '';
        if (inGrade) suffix = '（已包含）';
        else if (isPurchased) suffix = sa?.expired_at ? `（已購買，到期 ${sa.expired_at.substring(0, 10)}）` : '（已購買）';
        return `<option value="${a.id}" data-price="${a.price}" data-name="${a.name}" data-type="${a.type}" data-in-grade="${inGrade ? '1' : '0'}" ${inGrade ? 'disabled' : ''}>${a.name}${suffix}</option>`;
    }).join('');

    row.innerHTML = `
        <div class="flex items-end gap-2">
            <div class="flex-1">
                <label class="text-xs text-gray-500 mb-1 block">加購項目</label>
                <select class="addon-select w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 outline-none">
                    <option value="">— 選擇 Addon —</option>
                    ${addonOpts}
                </select>
            </div>
            <button type="button" class="remove-addon-row text-gray-400 hover:text-red-500 text-lg leading-none pb-2">✕</button>
        </div>
        <div class="grid grid-cols-4 gap-2 mt-2">
            <div class="addon-qty-col hidden">
                <label class="text-xs text-gray-500 mb-1 block">數量</label>
                <input type="number" min="1" value="1" class="addon-qty w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none">
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">開始日</label>
                <input type="date" class="addon-start-at w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none" min="${cfg.today}" value="${cfg.today}">
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">月數</label>
                <select class="addon-months w-full rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-blue-500 outline-none">
                    <option value="">—</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">金額</label>
                <input type="text" readonly class="addon-amount w-full rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-sm text-gray-700" placeholder="自動">
            </div>
        </div>
    `;

    const select = row.querySelector('.addon-select');
    const startAt = row.querySelector('.addon-start-at');
    const months  = row.querySelector('.addon-months');
    const qtyCol  = row.querySelector('.addon-qty-col');
    const qty     = row.querySelector('.addon-qty');
    const amount  = row.querySelector('.addon-amount');

    buildMonthsSelect(months, startAt.value);

    select.addEventListener('change', () => {
        const opt = select.selectedOptions[0];
        const isQuota = opt?.dataset?.type === '2';
        qtyCol.classList.toggle('hidden', !isQuota);
        markSelectedAddons();
        triggerAddonCalculate(row);
    });
    startAt.addEventListener('change', () => {
        buildMonthsSelect(months, startAt.value);
        triggerAddonCalculate(row);
    });
    months.addEventListener('change', () => triggerAddonCalculate(row));
    qty.addEventListener('input', () => triggerAddonCalculate(row));

    row.querySelector('.remove-addon-row').addEventListener('click', () => {
        row.remove();
        markSelectedAddons();
        updateOrderSummary();
        updateDiscountAndSummary();
        updateSubmitBlock();
    });

    addonRowsEl.appendChild(row);
    markSelectedAddons();
}

function markSelectedAddons() {
    const selectedIds = [...addonRowsEl.querySelectorAll('.addon-select')].map(s => s.value).filter(Boolean);
    addonRowsEl.querySelectorAll('.addon-row').forEach(row => {
        const sel = row.querySelector('.addon-select');
        sel.querySelectorAll('option').forEach(opt => {
            if (!opt.value) return;
            if (opt.dataset.inGrade === '1') { opt.disabled = true; return; }
            const alreadyInOtherRow = selectedIds.includes(opt.value) && sel.value !== opt.value;
            opt.disabled = alreadyInOtherRow;
            if (alreadyInOtherRow && !opt.textContent.includes('已加入')) {
                opt.textContent += '（已加入）';
            } else if (!alreadyInOtherRow) {
                opt.textContent = opt.textContent.replace('（已加入）', '');
            }
        });
    });
}

async function triggerAddonCalculate(row) {
    const opt = row.querySelector('.addon-select').selectedOptions[0];
    const startAt = row.querySelector('.addon-start-at').value;
    const months = row.querySelector('.addon-months').value;
    const qty = parseInt(row.querySelector('.addon-qty').value) || 1;
    const amountEl = row.querySelector('.addon-amount');

    amountEl.value = '';
    row._detail = null;

    if (!opt?.dataset?.price || !startAt || months === '') {
        updateOrderSummary();
        return;
    }

    const unitPrice = parseInt(opt.dataset.price) * qty;

    try {
        const res = await axios.get(cfg.calculateUrl, {
            params: { unit_price: parseInt(opt.dataset.price), start_at: startAt, total_months: parseInt(months), type: 3 },
        });
        const totalPrice = res.data.total_price * qty;
        amountEl.value = fmt(totalPrice);

        row._detail = {
            type: 3,
            name: opt.dataset.name,
            unit_price: parseInt(opt.dataset.price),
            total_price: totalPrice,
            start_at: startAt,
            expired_at: res.data.expired_at,
            total_months: parseInt(months),
            quantity: qty,
            payment_type: paymentTypeFromMonths(parseInt(months)),
        };
        updateOrderSummary();
        updateDiscountAndSummary();
        updateSubmitBlock();
    } catch { /* ignore */ }
}

// ─── Discount Block ───────────────────────────────────────────
const discountTypeEl   = document.getElementById('discount-type');
const discountAmountEl = document.getElementById('discount-amount');
const discountInfoEl   = document.getElementById('discount-info');
const discountErrorEl  = document.getElementById('discount-error');

discountTypeEl.addEventListener('change', () => {
    discountAmountEl.disabled = !discountTypeEl.value;
    discountAmountEl.value = '';
    discountInfoEl.classList.add('hidden');
    updateOrderSummary();
});

discountAmountEl.addEventListener('input', () => {
    const amount = parseInt(discountAmountEl.value) || 0;
    const subtotal = getSubtotal();
    discountErrorEl.classList.add('hidden');

    if (amount > subtotal) {
        discountErrorEl.textContent = `折抵金額（NT$${amount.toLocaleString()}）不得大於小計（NT$${subtotal.toLocaleString()}）`;
        discountErrorEl.classList.remove('hidden');
    } else if (amount > 0) {
        const name = discountTypeEl.selectedOptions[0]?.dataset?.name ?? '';
        discountInfoEl.textContent = `套用：${name} NT$${amount.toLocaleString()}`;
        discountInfoEl.classList.remove('hidden');
    } else {
        discountInfoEl.classList.add('hidden');
    }
    updateOrderSummary();
    updateSubmitBlock();
});

function updateDiscountAndSummary() {
    const hasItems = getSubtotal() > 0;
    if (hasItems) {
        show('discount-block');
    } else {
        hide('discount-block');
    }
}

// ─── Order Summary ────────────────────────────────────────────
function getSubtotal() {
    let s = 0;
    gradeDetails.forEach(d => s += d.total_price);
    addonRowsEl.querySelectorAll('.addon-row').forEach(row => {
        if (row._detail) s += row._detail.total_price;
    });
    return s;
}

function updateOrderSummary() {
    const summaryEl = document.getElementById('order-summary');
    const rowsEl    = document.getElementById('summary-rows');
    const subtotalEl = document.getElementById('summary-subtotal');
    const discountRowEl = document.getElementById('summary-discount-row');
    const discountValEl = document.getElementById('summary-discount-val');
    const totalEl    = document.getElementById('summary-total');

    const allDetails = [];
    gradeDetails.forEach(d => allDetails.push(d));
    addonRowsEl.querySelectorAll('.addon-row').forEach(row => {
        if (row._detail) allDetails.push(row._detail);
    });

    if (allDetails.length === 0) { summaryEl.classList.add('hidden'); return; }
    summaryEl.classList.remove('hidden');

    rowsEl.innerHTML = allDetails.map(d => `
        <tr>
            <td class="py-1.5 text-gray-700">${d.name}</td>
            <td class="py-1.5 text-gray-500 text-xs">${d.start_at?.substring(0,10)} → ${d.expired_at?.substring(0,10)}</td>
            <td class="py-1.5 text-right font-medium text-gray-900">${fmt(d.total_price)}</td>
        </tr>
    `).join('');

    const subtotal = getSubtotal();
    const discAmount = parseInt(discountAmountEl.value) || 0;
    const total = Math.max(0, subtotal - discAmount);

    subtotalEl.textContent = fmt(subtotal);
    if (discAmount > 0 && discAmount <= subtotal) {
        discountRowEl.classList.remove('hidden');
        discountValEl.textContent = `−${fmt(discAmount)}`;
    } else {
        discountRowEl.classList.add('hidden');
    }
    totalEl.textContent = fmt(total);
}

// ─── Submit Block ─────────────────────────────────────────────
function updateSubmitBlock() {
    const allDetails = getAllDetails();
    const hasItems = allDetails.length > 0;
    if (hasItems) {
        show('payment-method-block');
        show('submit-block');
        buildFormInputs(allDetails);
    } else {
        hide('payment-method-block');
        hide('submit-block');
    }
}

function getAllDetails() {
    const details = [];
    gradeDetails.forEach(d => details.push(d));
    addonRowsEl.querySelectorAll('.addon-row').forEach(row => {
        if (row._detail) details.push(row._detail);
    });
    return details;
}

function buildFormInputs(details) {
    const container = document.getElementById('form-details-container');
    container.innerHTML = '';

    document.getElementById('form-shop-id').value = shopData?.shop?.id ?? '';
    document.getElementById('form-payment-method').value = document.getElementById('payment-method-select')?.value ?? 2;

    details.forEach((d, i) => {
        const fields = { type: d.type, payment_type: d.payment_type, quantity: d.quantity, unit_price: d.unit_price, total_price: d.total_price, name: d.name, start_at: d.start_at, expired_at: d.expired_at, total_months: d.total_months };
        Object.entries(fields).forEach(([k, v]) => {
            if (v == null) return;
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = `details[${i}][${k}]`;
            inp.value = v;
            container.appendChild(inp);
        });
    });

    const discAmount = parseInt(discountAmountEl.value) || 0;
    const discName   = discountTypeEl.selectedOptions[0]?.dataset?.name ?? '';
    document.getElementById('form-discount-amount').value = discAmount > 0 ? discAmount : '';
    document.getElementById('form-discount-name').value   = discAmount > 0 ? discName : '';
}

// Validate on submit
document.getElementById('bill-form').addEventListener('submit', e => {
    const subtotal = getSubtotal();
    const discAmount = parseInt(discountAmountEl.value) || 0;
    if (discAmount > subtotal) {
        e.preventDefault();
        discountErrorEl.textContent = `折抵金額不得大於小計`;
        discountErrorEl.classList.remove('hidden');
    }
    if (!shopData?.shop?.id) {
        e.preventDefault();
        alert('請先搜尋並確認商店');
    }
});
