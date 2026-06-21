import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import ImageUploadField from '@/addons/ImageUploadField.vue';

function setupDOM({ hasImage = false, pendingRemove = false } = {}) {
    const previewHidden = hasImage ? '' : ' hidden';
    const previewSrc = hasImage ? 'http://example.com/image.jpg' : '';
    const placeholderHidden = hasImage ? ' hidden' : '';
    const overlayHidden = hasImage ? '' : ' hidden';
    const removeBtnHidden = hasImage ? '' : ' hidden';

    document.body.innerHTML = `
        <div id="image-preview-wrap">
            <img id="image-preview" src="${previewSrc}" alt=""
                 class="w-full h-full object-cover${previewHidden}">
            <div id="image-placeholder" class="flex flex-col items-center gap-2${placeholderHidden}"></div>
            <div id="image-overlay" class="absolute inset-0${overlayHidden}"></div>
        </div>
        <input type="file" name="image" id="image" accept=".jpg,.jpeg,.png" class="hidden">
        <input type="hidden" name="remove_image" id="remove_image" value="${pendingRemove ? '1' : '0'}">
        <div class="mt-3 flex items-center gap-2">
            <button type="button" id="image-remove-btn"
                    class="inline-flex items-center${removeBtnHidden}">
                刪除圖片
            </button>
            <span id="image-filename" class="text-sm text-gray-500"></span>
        </div>
        <div id="image-upload-field"></div>
    `;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('ImageUploadField', () => {
    describe('(a) 選擇檔案', () => {
        beforeEach(() => setupDOM());

        it('change 後 remove_image 變 0、filename 顯示檔名、預覽顯示、placeholder 隱藏、remove 鈕顯示', async () => {
            mount(ImageUploadField, { attachTo: '#image-upload-field' });

            const input = document.getElementById('image');
            const removeFlag = document.getElementById('remove_image');
            const filenamEl = document.getElementById('image-filename');
            const preview = document.getElementById('image-preview');
            const holder = document.getElementById('image-placeholder');
            const removeBtn = document.getElementById('image-remove-btn');

            const file = new File(['x'], 'a.png', { type: 'image/png' });
            Object.defineProperty(input, 'files', { value: [file], configurable: true });

            input.dispatchEvent(new Event('change'));

            expect(removeFlag.value).toBe('0');
            expect(filenamEl.textContent).toBe('a.png');

            // Wait for FileReader onload (async) — robust regardless of timer state
            await vi.waitFor(() => {
                expect(preview.classList.contains('hidden')).toBe(false);
            });
            await flushPromises();
            expect(holder.classList.contains('hidden')).toBe(true);
            expect(removeBtn.classList.contains('hidden')).toBe(false);
            expect(preview.getAttribute('src')).toMatch(/^data:/);
        });
    });

    describe('(b) 點擊刪除按鈕', () => {
        beforeEach(() => setupDOM({ hasImage: true }));

        it('remove_image 變 1、preview 隱藏、placeholder 顯示、remove 鈕隱藏', async () => {
            mount(ImageUploadField, { attachTo: '#image-upload-field' });

            const removeFlag = document.getElementById('remove_image');
            const preview = document.getElementById('image-preview');
            const holder = document.getElementById('image-placeholder');
            const removeBtn = document.getElementById('image-remove-btn');

            expect(removeFlag.value).toBe('0');
            expect(preview.classList.contains('hidden')).toBe(false);
            expect(holder.classList.contains('hidden')).toBe(true);
            expect(removeBtn.classList.contains('hidden')).toBe(false);

            removeBtn.click();
            await flushPromises();

            expect(removeFlag.value).toBe('1');
            expect(preview.classList.contains('hidden')).toBe(true);
            expect(holder.classList.contains('hidden')).toBe(false);
            expect(removeBtn.classList.contains('hidden')).toBe(true);
        });
    });

    describe('(c) 驗證失敗重渲染：pendingRemove=true 有初始圖', () => {
        beforeEach(() => setupDOM({ hasImage: true, pendingRemove: true }));

        it('初始化後 placeholder 顯示、remove 鈕隱藏', async () => {
            mount(ImageUploadField, { attachTo: '#image-upload-field' });
            await flushPromises();

            const preview = document.getElementById('image-preview');
            const holder = document.getElementById('image-placeholder');
            const overlay = document.getElementById('image-overlay');
            const removeBtn = document.getElementById('image-remove-btn');

            expect(preview.classList.contains('hidden')).toBe(true);
            expect(holder.classList.contains('hidden')).toBe(false);
            expect(overlay.classList.contains('hidden')).toBe(true);
            expect(removeBtn.classList.contains('hidden')).toBe(true);
        });
    });

    describe('(d) 無初始圖時移除鈕隱藏', () => {
        beforeEach(() => setupDOM({ hasImage: false }));

        it('初始化後 remove 鈕應保持隱藏', async () => {
            mount(ImageUploadField, { attachTo: '#image-upload-field' });
            await flushPromises();

            const removeBtn = document.getElementById('image-remove-btn');
            expect(removeBtn.classList.contains('hidden')).toBe(true);
        });
    });
});
