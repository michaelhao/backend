document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-shop-edit]');
    if (!form) return;

    initAdminEmailToggle(form);
    initCertModal(form);
});

function initAdminEmailToggle(form) {
    const emailMasked = document.getElementById('admin-email-masked');
    const emailInput = document.getElementById('admin-email-input');
    const emailToggle = document.getElementById('admin-email-toggle');
    if (!emailToggle) return;

    emailToggle.addEventListener('click', () => {
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

    if (form.dataset.adminEmailError === '1') {
        emailToggle.click();
    }
}

function initCertModal(form) {
    const certModal = document.getElementById('cert-modal');
    const openCertBtn = document.getElementById('open-cert-modal');
    const certClose = document.getElementById('cert-modal-close');
    const certSubmit = document.getElementById('cert-submit');
    const certInput = document.getElementById('cert-business-number');
    const certResult = document.getElementById('cert-result');
    const certError = document.getElementById('cert-input-error');
    if (!certModal) return;

    const certRoute = form.dataset.certRoute;

    function closeCertModal() {
        certModal.classList.add('hidden');
    }

    openCertBtn?.addEventListener('click', () => {
        certInput.value = '';
        certResult.classList.add('hidden');
        certError.classList.add('hidden');
        certSubmit.disabled = false;
        certSubmit.textContent = '認證';
        certModal.classList.remove('hidden');
    });

    certClose?.addEventListener('click', closeCertModal);
    certModal.addEventListener('click', (e) => {
        if (e.target === certModal) closeCertModal();
    });

    certInput?.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    certSubmit?.addEventListener('click', async () => {
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
            const response = await fetch(certRoute, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ business_number: bn }),
            });

            const data = await response.json();

            if (data.success) {
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
}

// 與後端 App\Support\Mask::string() 同演算法（奇數索引換 *）；兩端須同步修改
function maskString(value) {
    return value.split('').map((c, i) => (i % 2 === 1 ? '*' : c)).join('');
}
