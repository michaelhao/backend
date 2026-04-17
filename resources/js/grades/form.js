import axios from 'axios';

document.addEventListener('DOMContentLoaded', () => {
    const input   = document.getElementById('weight');
    const errorEl = document.getElementById('weight-error');
    const listEl  = document.getElementById('weight-list');
    if (!input) return;

    const excludeId = input.dataset.excludeId || null;
    const isEdit    = !!excludeId;

    input.addEventListener('change', async function () {
        const weight = this.value.trim();

        listEl.querySelectorAll('.weight-row').forEach(r => r.classList.remove('text-red-600', 'font-semibold', 'text-blue-600', 'font-medium'));
        listEl.querySelectorAll('.weight-preview').forEach(r => r.remove());
        errorEl.classList.add('hidden');
        errorEl.textContent = '';

        if (!weight) return;

        if (parseInt(weight) < 1) {
            errorEl.textContent = '版本權重最低為 1';
            errorEl.classList.remove('hidden');
            return;
        }

        let data;
        try {
            ({ data } = await axios.get('/grades/check-weight', {
                params: { weight, exclude_id: excludeId || undefined },
            }));
        } catch {
            return;
        }

        if (data.duplicate) {
            errorEl.textContent = '請確認版本權重';
            errorEl.classList.remove('hidden');
            const conflictRow = listEl.querySelector(`.weight-row[data-id="${data.conflicting_grade.id}"]`);
            if (conflictRow) conflictRow.classList.add('text-red-600', 'font-semibold');
            return;
        }

        const rows    = [...listEl.querySelectorAll('.weight-row')];
        const afterRow = rows.find(r => {
            if (isEdit && r.dataset.id == excludeId) return false;
            const g = data.grades.find(g => g.id == r.dataset.id);
            return g && g.weight < parseInt(weight);
        });

        if (isEdit) {
            const currentRow = listEl.querySelector(`.weight-row[data-id="${excludeId}"]`);
            if (currentRow) {
                currentRow.querySelectorAll('span')[1].textContent = weight;
                currentRow.classList.add('text-blue-600', 'font-medium');
                afterRow ? listEl.insertBefore(currentRow, afterRow) : listEl.appendChild(currentRow);
            }
        } else {
            const nameInput = document.getElementById('name');
            const label     = nameInput && nameInput.value.trim() ? nameInput.value.trim() : '（設定位置）';
            const preview    = document.createElement('div');
            preview.className = 'flex justify-between weight-preview text-blue-600 font-medium';
            const nameSpan   = document.createElement('span');
            const weightSpan = document.createElement('span');
            nameSpan.textContent   = label;
            weightSpan.textContent = weight;
            preview.append(nameSpan, weightSpan);
            afterRow ? listEl.insertBefore(preview, afterRow) : listEl.appendChild(preview);
        }
    });

    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.addEventListener('input', function () {
            const label = this.value.trim() || '（設定位置）';
            if (isEdit) {
                const currentRow = listEl.querySelector(`.weight-row[data-id="${excludeId}"] span`);
                if (currentRow) currentRow.textContent = label;
            } else {
                const preview = listEl.querySelector('.weight-preview span');
                if (preview) preview.textContent = label;
            }
        });
    }
});
