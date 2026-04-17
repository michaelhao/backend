# Fix: 刪除操作從 Form POST 改為 Axios

## 問題描述

目前以下三個模組的刪除操作採用 HTML form POST + 瀏覽器原生 `confirm()` 方式：

- 整頁 reload，UX 較差
- 確認 dialog 為瀏覽器原生，無法客製化樣式
- 表格內放 `<form>` 語義不夠乾淨

應改為 **axios DELETE + 自訂確認 Modal**，刪除成功後直接移除 DOM 列，不需整頁重載。

---

## 受影響範圍

| 模組 | View | Controller |
| :--- | :--- | :--- |
| 角色管理 | `resources/views/admin/roles/index.blade.php` L50-55 | `app/Http/Controllers/RoleController.php` L72 `destroy()` |
| 使用者管理 | `resources/views/admin/users/index.blade.php` L46-52 | `app/Http/Controllers/UserController.php` L70 `destroy()` |
| 附加功能管理 | `resources/views/admin/addons/index.blade.php` L155-161 | `app/Http/Controllers/AddonController.php` L70 `destroy()` |

---

## 修改規格

### 1. Controller `destroy()` — 改回傳 JSON

三個 Controller 的 `destroy()` 回傳從 `redirect()` 改為 JSON：

**成功**
```php
return response()->json(['message' => '已刪除']);
```

**失敗（找不到 / 業務邏輯錯誤）**
```php
return response()->json(['message' => '錯誤原因'], 422);
```

各模組現有的業務邏輯錯誤條件保留：

| 模組 | 現有錯誤條件 |
| :--- | :--- |
| Role | 角色仍有使用者，無法刪除 |
| User | 無法刪除自己的帳號 |
| Addon | Addon 已被刪除（status = -1） |

---

### 2. View — 移除 inline form，改用 data attribute

**Before**
```html
<form method="POST" action="{{ route('roles.destroy', $role) }}" class="inline"
      onsubmit="return confirm('確定要刪除此角色嗎？')">
    @csrf
    @method('DELETE')
    <button type="submit" class="text-red-600 hover:text-red-800">刪除</button>
</form>
```

**After**
```html
<button type="button"
        class="delete-btn text-red-600 hover:text-red-800"
        data-url="{{ route('roles.destroy', $role) }}"
        data-name="{{ $role->name }}">
    刪除
</button>
```

- `data-url`：DELETE 請求目標
- `data-name`：顯示在確認 Modal 中的項目名稱

---

### 3. 共用確認 Modal

各頁面底部加入一個共用確認 Modal（各頁自行維護，不抽共用元件）：

```html
<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">確認刪除</h3>
        <p class="text-sm text-gray-600 mb-6">
            確定要刪除「<span id="delete-modal-name" class="font-medium text-gray-900"></span>」嗎？此操作無法復原。
        </p>
        <div class="flex justify-end gap-3">
            <button id="delete-modal-cancel"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                取消
            </button>
            <button id="delete-modal-confirm"
                    class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors">
                確認刪除
            </button>
        </div>
    </div>
</div>
```

---

### 4. JS 邏輯

```js
let deleteTargetUrl = null;

// 綁定所有刪除按鈕
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        deleteTargetUrl = this.dataset.url;
        document.getElementById('delete-modal-name').textContent = this.dataset.name;
        document.getElementById('delete-modal').classList.remove('hidden');
    });
});

// 取消
document.getElementById('delete-modal-cancel').addEventListener('click', () => {
    document.getElementById('delete-modal').classList.add('hidden');
    deleteTargetUrl = null;
});

// 確認刪除
document.getElementById('delete-modal-confirm').addEventListener('click', async function () {
    if (!deleteTargetUrl) return;

    this.disabled = true;
    this.textContent = '刪除中...';

    try {
        await axios.delete(deleteTargetUrl);

        // 移除對應的 <tr>
        document.querySelector(`[data-url="${deleteTargetUrl}"]`)
            .closest('tr')
            .remove();

        showFlash('success', '已成功刪除');
    } catch (err) {
        const message = err.response?.data?.message ?? '刪除失敗，請稍後再試';
        showFlash('error', message);
    } finally {
        document.getElementById('delete-modal').classList.add('hidden');
        this.disabled = false;
        this.textContent = '確認刪除';
        deleteTargetUrl = null;
    }
});

// Flash 訊息（動態插入）
function showFlash(type, message) {
    const colors = {
        success: 'bg-green-50 text-green-700',
        error:   'bg-red-50 text-red-700',
    };
    const el = document.createElement('div');
    el.className = `mb-4 rounded-lg p-4 text-sm flash-message ${colors[type]}`;
    el.textContent = message;
    document.querySelector('.flash-area').prepend(el);
    setTimeout(() => {
        el.style.opacity = '0';
        el.style.transition = 'opacity 0.5s';
        setTimeout(() => el.remove(), 500);
    }, 5000);
}
```

> **Flash 區塊**：各頁面原有 `session('success')` / `session('error')` 的位置，外層加上 `<div class="flash-area">` wrapper，供 JS 動態插入 flash 訊息。

---

## Axios CSRF 設定

確認 `resources/js/app.js` 或 layout 已設定 axios 預設 header（Laravel 預設已包含）：

```js
axios.defaults.headers.common['X-CSRF-TOKEN'] = document
    .querySelector('meta[name="csrf-token"]').getAttribute('content');
```

---

## 注意事項

- `destroy()` 改回 JSON 後，原本 `RedirectResponse` return type 需改為 `JsonResponse`
- Addon 的 `destroy()` 有軟刪除邏輯（status = -1），不影響 JSON 回傳
- Role 刪除有「仍有使用者」的業務邏輯錯誤，需以 422 + message 回傳
- User 刪除有「不能刪除自己」的業務邏輯錯誤，同上
