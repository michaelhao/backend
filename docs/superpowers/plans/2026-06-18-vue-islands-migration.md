# 原生 JS → Vue 島嶼 全面遷移 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 把本專案 `resources/js` 下 18 支、約 2,077 行的原生 JS(對 Blade 已渲染 DOM 做命令式漸進增強)全面改寫為「Blade + Vue 3 島嶼」——每個 `@vite` entry 改為把一個 Vue 元件掛載到 Blade 渲染的掛載點,以響應式狀態取代命令式 DOM 操作。

**Architecture:** 維持 Laravel Blade 伺服器渲染與路由不變;每頁的互動區塊由 Blade 輸出「掛載點 + `@json` 初始資料」,對應的 `@vite` entry 用共用 `mountIsland()` 把 `.vue` 元件掛上去。後端資料以 props 傳入(取代 `data-*`/DOM 讀取),AJAX 走共用 `http` 模組(包裝既有 axios),即時功能與未讀 badge 收斂成 composable。逐島遷移、可獨立上線與回退;舊原生 JS 與新 Vue 島在不同頁面長期並存,無「切換日」。

**Tech Stack:** Laravel 13 / PHP 8.5(Docker)、Blade、**Vue 3 + `@vitejs/plugin-vue`**、Vite 8、Tailwind v4(`@tailwindcss/vite`)、axios、laravel-echo + pusher-js(Reverb)、**Vitest + @vue/test-utils + jsdom**(新增前端測試)、PHPUnit 12(後端不動)。

## Global Constraints

- **所有 npm / artisan / composer 指令一律在 Docker 容器內執行**:`docker compose exec backend-api <cmd>`(服務名)或 `docker exec wsl-backend <cmd>`(容器名)。不要在 host 直接跑。
- **依賴變更需使用者核准**:新增 `vue`、`@vitejs/plugin-vue`、`vitest`、`@vue/test-utils`、`@vue/test-utils` 依賴、`jsdom` 屬於 dependency 變更(Laravel Boost 規則)。M0 安裝步驟執行前必須先取得使用者同意。
- **不動後端**:Laravel 路由 / Controller / Service / Repository / Model / 驗證 / 權限 / 認證 / CSRF / migration 一律不改。本計畫只動 `resources/js/**`、`resources/views/**`(僅互動區塊掛載點)、`vite.config.js`、`package.json`、新增 `vitest.config.js`。
- **Tailwind v4 設計系統不分叉**:沿用 `resources/css/app.css` 的 `@theme` token 與既有元件類(`.form-control`、`.modal-overlay`、`.btn-primary`、`.flash-*`、`.chat-*` 等)。Vue SFC 用 Tailwind utility class;**避免** `<style scoped>`,維持單一設計系統來源。改色後跑 `npm run build` 驗證。
- **JS 契約 class 規則的調整**:CLAUDE.md「JS 契約類別不可包進元件類」(`hidden` 切換、`.flash-message`、`.flash-area`、`#delete-modal*`、`.delete-btn`)是為命令式 JS 而設。**島內**改由 Vue 狀態驅動(`v-if`/`v-show`),這些 hook class 在島內不再需要;**島外**(尚未遷移的頁)仍維持原規則。每次遷移一個島,連同移除該島不再使用的 hook class。
- **每島完成後**:`docker compose exec backend-api npm run test`(該島的 Vitest)綠燈 + `docker compose exec backend-api npm run build` 成功。
- **Pint**:本計畫幾乎不動 PHP;若任何任務改到 `.php`,結束前跑 `docker exec wsl-backend vendor/bin/pint --dirty --format agent`。
- **Commit**:由人工撰寫 / 同意才 commit;message 結尾加 `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`,且項目須與實際 staged diff 相符。各任務的 commit step 為建議檢查點。
- **分支**:本計畫在 `feat/vue-islands-migration` 上執行。

---

## File Structure

**新增(基建 / 共用)**
- `vitest.config.js` — Vitest 設定(jsdom 環境、`@` alias、globals)。
- `resources/js/lib/http.js` — re-export 設定好的 axios 實例(供元件 import,Vitest 可 mock)。
- `resources/js/lib/mountIsland.js` — 共用掛載器:依 id 找掛載點、解析 `data-props` JSON、`createApp(Component, props).mount(el)`。
- `resources/js/composables/useFlash.js` — flash 提示(包裝既有 `.flash-area` DOM,跨島共用)。
- `resources/js/composables/useConfirmModal.js` + `resources/js/components/ConfirmModal.vue` — 共用確認 modal。
- `resources/js/composables/useEcho.js` — 取用 `window.Echo`(Reverb)的薄封裝。
- `resources/js/composables/useChatBadge.js` — 全站未讀 badge(取代 `window.refreshChatBadge`)。

**改寫(每島:1 個 `.vue` + 改 entry + 改 Blade 掛載點 + 1 個 `.test.js`)**
- M1:`components/searchable-select`、`grades/form`
- M2:`shops/edit`、`shops/index`、`addons/form`、`grades/index`、`addons/index`、`users/index`、`roles/index`、`conferences/index`、`layouts/admin`
- M3:`bills/create`、`bills/index`
- M4:`chats/index`(+ `bootstrap.js` 的 Echo/badge 收斂)

**慣例(每島一致)**
- `.vue` 元件放在該 feature 目錄:`resources/js/<feature>/<Name>.vue`(共用元件放 `resources/js/components/`)。
- entry(`resources/js/<feature>/index.js` 等)只負責 `mountIsland('<mount-id>', Component)`。
- Blade 掛載點:`<div id="<mount-id>" data-props="@json($props)"></div>`,緊接 `@vite(...)`。
- 測試與元件同目錄:`resources/js/<feature>/<Name>.test.js`。

---

## M0 — Build 基建

### Task 0.1: 安裝 Vue 與測試依賴(需使用者核准)

**Files:**
- Modify: `package.json`

- [ ] **Step 1: 取得使用者同意新增依賴**

口頭 / 對話確認後再執行下一步(Global Constraints:依賴變更需核准)。

- [ ] **Step 2: 安裝**

Run:
```bash
docker compose exec backend-api npm install vue @vitejs/plugin-vue
docker compose exec backend-api npm install -D vitest @vue/test-utils jsdom
```
Expected: `package.json` 出現 `vue`、`@vitejs/plugin-vue`(dependencies / devDependencies)與 `vitest`、`@vue/test-utils`、`jsdom`(devDependencies)。

- [ ] **Step 3: 加上 test script**

修改 `package.json` 的 `scripts`:
```json
"scripts": {
    "build": "vite build",
    "dev": "vite",
    "test": "vitest run",
    "test:watch": "vitest"
}
```

- [ ] **Step 4: Commit**
```bash
git add package.json package-lock.json
git commit -m "build: 加入 Vue 與 Vitest 依賴"
```

### Task 0.2: vite.config 加 Vue plugin 與 `@` alias

**Files:**
- Modify: `vite.config.js`

**Interfaces:**
- Produces: `@` alias → `resources/js`;Vite 能編譯 `.vue`。

- [ ] **Step 1: 修改 `vite.config.js`**

在現有設定加入 `vue()` plugin(放在 `laravel(...)` 之後、`tailwindcss()` 之前)與 `resolve.alias`。保留所有現有 `input`、`server` 設定不動:
```js
import { defineConfig } from 'vite';
import { fileURLToPath, URL } from 'node:url';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/layouts/admin.js',
                'resources/js/addons/index.js',
                'resources/js/addons/form.js',
                'resources/js/users/index.js',
                'resources/js/roles/index.js',
                'resources/js/shops/index.js',
                'resources/js/shops/edit.js',
                'resources/js/grades/index.js',
                'resources/js/grades/form.js',
                'resources/js/components/searchable-select.js',
                'resources/js/bills/index.js',
                'resources/js/bills/create.js',
                'resources/js/conferences/index.js',
                'resources/js/chats/index.js',
            ],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: { host: 'localhost' },
        watch: { usePolling: true },
    },
});
```

- [ ] **Step 2: 驗證 build**

Run: `docker compose exec backend-api npm run build`
Expected: build 成功(尚無 `.vue`,僅驗證設定無誤)。

- [ ] **Step 3: Commit**
```bash
git add vite.config.js
git commit -m "build: vite 加入 vue plugin 與 @ alias"
```

### Task 0.3: vitest.config 與測試環境

**Files:**
- Create: `vitest.config.js`

**Interfaces:**
- Produces: `npm run test` 能跑 `.test.js`、解析 `.vue` 與 `@` alias、jsdom 環境、`describe/it/expect` globals。

- [ ] **Step 1: 建立 `vitest.config.js`**
```js
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['resources/js/**/*.test.js'],
    },
});
```

- [ ] **Step 2: 驗證(暫無測試應為 0 passed,不報錯)**

Run: `docker compose exec backend-api npm run test`
Expected: 「No test files found」或 0 failed(不可報設定錯誤)。

- [ ] **Step 3: Commit**
```bash
git add vitest.config.js
git commit -m "test: 加入 vitest 設定(jsdom + @ alias)"
```

### Task 0.4: 共用 `http` 模組

**Files:**
- Create: `resources/js/lib/http.js`
- Test: `resources/js/lib/http.test.js`

**Interfaces:**
- Produces: `export default http`(axios 實例,預設帶 `X-Requested-With: XMLHttpRequest`)。元件一律 `import http from '@/lib/http'`,測試以 `vi.mock('@/lib/http')` 取代。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/lib/http.test.js
import { describe, it, expect } from 'vitest';
import http from '@/lib/http';

describe('http', () => {
    it('預設帶 X-Requested-With header', () => {
        expect(http.defaults.headers.common['X-Requested-With']).toBe('XMLHttpRequest');
    });
    it('是一個帶 get/post 的 axios 實例', () => {
        expect(typeof http.get).toBe('function');
        expect(typeof http.post).toBe('function');
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/http.test.js`
Expected: FAIL(找不到 `@/lib/http`)。

- [ ] **Step 3: 實作**
```js
// resources/js/lib/http.js
import axios from 'axios';

const http = axios.create();
http.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

export default http;
```

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/http.test.js`
Expected: PASS（2 個）。

- [ ] **Step 5: Commit**
```bash
git add resources/js/lib/http.js resources/js/lib/http.test.js
git commit -m "feat(js): 加入共用 http 模組"
```

### Task 0.5: 共用 `mountIsland` 掛載器

**Files:**
- Create: `resources/js/lib/mountIsland.js`
- Test: `resources/js/lib/mountIsland.test.js`

**Interfaces:**
- Produces: `mountIsland(mountId: string, Component, extraProps = {}): App | null`。讀 `document.getElementById(mountId)`;若不存在回傳 `null`(該頁沒有這個島);若元素有 `data-props` 則 `JSON.parse` 後與 `extraProps` 合併為 props 掛載。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/lib/mountIsland.test.js
import { describe, it, expect, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import mountIsland from '@/lib/mountIsland';

const Probe = defineComponent({
    props: { label: { type: String, default: '' } },
    setup: (props) => () => h('span', { class: 'probe' }, props.label),
});

beforeEach(() => { document.body.innerHTML = ''; });

describe('mountIsland', () => {
    it('掛載點不存在時回傳 null', () => {
        expect(mountIsland('missing', Probe)).toBeNull();
    });

    it('解析 data-props 並當成 props 掛載', () => {
        document.body.innerHTML =
            '<div id="app" data-props=\'{"label":"hello"}\'></div>';
        mountIsland('app', Probe);
        expect(document.querySelector('.probe').textContent).toBe('hello');
    });

    it('沒有 data-props 也能掛載', () => {
        document.body.innerHTML = '<div id="app2"></div>';
        const app = mountIsland('app2', Probe);
        expect(app).not.toBeNull();
        expect(document.querySelector('.probe')).not.toBeNull();
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/mountIsland.test.js`
Expected: FAIL(找不到模組)。

- [ ] **Step 3: 實作**
```js
// resources/js/lib/mountIsland.js
import { createApp } from 'vue';

export default function mountIsland(mountId, Component, extraProps = {}) {
    const el = document.getElementById(mountId);
    if (!el) {
        return null;
    }
    let props = {};
    if (el.dataset.props) {
        try {
            props = JSON.parse(el.dataset.props);
        } catch {
            props = {};
        }
    }
    const app = createApp(Component, { ...props, ...extraProps });
    app.mount(el);
    return app;
}
```

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/mountIsland.test.js`
Expected: PASS（3 個）。

- [ ] **Step 5: Commit**
```bash
git add resources/js/lib/mountIsland.js resources/js/lib/mountIsland.test.js
git commit -m "feat(js): 加入共用 mountIsland 掛載器"
```

### Task 0.6: hello-island 煙霧測試(驗證端到端基建)

**Files:**
- Create: `resources/js/lib/HelloIsland.vue`
- Test: `resources/js/lib/HelloIsland.test.js`

**Interfaces:**
- Produces: 一個最小 `.vue`,證明 `.vue` 能編譯、`mountIsland` 能掛、Vitest 能測元件。完成驗證後在 Task 0.7 刪除。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/lib/HelloIsland.test.js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HelloIsland from '@/lib/HelloIsland.vue';

describe('HelloIsland', () => {
    it('渲染傳入的 name', () => {
        const wrapper = mount(HelloIsland, { props: { name: '世界' } });
        expect(wrapper.text()).toContain('Hello 世界');
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/HelloIsland.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作**
```vue
<!-- resources/js/lib/HelloIsland.vue -->
<script setup>
defineProps({ name: { type: String, default: 'island' } });
</script>

<template>
  <p>Hello {{ name }}</p>
</template>
```

- [ ] **Step 4: 跑測試 + build 確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/lib/HelloIsland.test.js`
Expected: PASS。

- [ ] **Step 5: Commit**
```bash
git add resources/js/lib/HelloIsland.vue resources/js/lib/HelloIsland.test.js
git commit -m "test(js): hello-island 端到端煙霧測試"
```

### Task 0.7: 清掉 hello-island

**Files:**
- Delete: `resources/js/lib/HelloIsland.vue`, `resources/js/lib/HelloIsland.test.js`

- [ ] **Step 1: 刪除煙霧檔**
```bash
git rm resources/js/lib/HelloIsland.vue resources/js/lib/HelloIsland.test.js
```

- [ ] **Step 2: 驗證測試仍綠**

Run: `docker compose exec backend-api npm run test`
Expected: 僅剩 `http`、`mountIsland` 測試,全 PASS。

- [ ] **Step 3: Commit**
```bash
git commit -m "chore(js): 移除 hello-island 煙霧檔"
```

---

## M1 — 試點(立慣例,風險低)

> M1 的兩個島是後續所有島的「參考實作」:示範 props 傳入、http mock 測試、`v-model`/`v-if` 取代命令式 DOM。後面批次比照辦理。

### Task 1.1: `SearchableSelect.vue` 元件 + 測試

來源:`resources/js/components/searchable-select.js`(81 行)、`resources/views/components/searchable-select.blade.php`。
行為:可搜尋下拉;輸入過濾 group/option;點選寫入 hidden input 值並高亮;點外面關閉,且若沒選任何值就清空文字框。

**Files:**
- Create: `resources/js/components/SearchableSelect.vue`
- Test: `resources/js/components/SearchableSelect.test.js`

**Interfaces:**
- Produces props: `name: string`、`value: string`(預設選中)、`placeholder: string`、`groups: Array<{ module: string, options: Array<{ value, label, search }> }>`。
- 渲染一個 `<input type="hidden" :name="name" :value="selected">` 供既有表單 POST(沿用原本欄位語意)。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/components/SearchableSelect.test.js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchableSelect from '@/components/SearchableSelect.vue';

const groups = [
    { module: '使用者', options: [
        { value: 'User.index', label: '使用者 - 列表', search: '使用者 列表 user.index' },
        { value: 'User.create', label: '使用者 - 新增', search: '使用者 新增 user.create' },
    ] },
    { module: '角色', options: [
        { value: 'Role.index', label: '角色 - 列表', search: '角色 列表 role.index' },
    ] },
];

const factory = (props = {}) =>
    mount(SearchableSelect, { props: { name: 'default_route', value: '', placeholder: '搜尋', groups, ...props } });

describe('SearchableSelect', () => {
    it('hidden input 帶 name 與初始 value', () => {
        const w = factory({ value: 'Role.index' });
        const hidden = w.get('input[type="hidden"]');
        expect(hidden.attributes('name')).toBe('default_route');
        expect(hidden.element.value).toBe('Role.index');
    });

    it('輸入關鍵字過濾出符合的 option', async () => {
        const w = factory();
        await w.get('.ss-input').setValue('角色');
        const visible = w.findAll('.ss-option').filter((o) => o.isVisible());
        expect(visible).toHaveLength(1);
        expect(visible[0].text()).toContain('角色 - 列表');
    });

    it('點選 option 寫入 hidden 值並顯示 label', async () => {
        const w = factory();
        await w.get('.ss-input').setValue('使用者');
        await w.findAll('.ss-option')[0].trigger('click');
        expect(w.get('input[type="hidden"]').element.value).toBe('User.index');
        expect(w.get('.ss-input').element.value).toBe('使用者 - 列表');
    });

    it('無符合時顯示無結果', async () => {
        const w = factory();
        await w.get('.ss-input').setValue('zzz不存在');
        expect(w.get('.ss-no-results').isVisible()).toBe(true);
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/components/SearchableSelect.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `SearchableSelect.vue`**
```vue
<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    value: { type: String, default: '' },
    placeholder: { type: String, default: '搜尋或選擇…' },
    groups: { type: Array, default: () => [] },
});

const selected = ref(props.value);
const open = ref(false);
const query = ref('');

const initialLabel = () => {
    for (const g of props.groups) {
        const found = g.options.find((o) => o.value === props.value);
        if (found) { return found.label; }
    }
    return '';
};
const text = ref(initialLabel());

const matches = (o, moduleLabel) => {
    const q = query.value.toLowerCase().trim();
    return !q || o.search.includes(q) || moduleLabel.toLowerCase().includes(q);
};

const visibleGroups = computed(() =>
    props.groups
        .map((g) => ({ ...g, options: g.options.filter((o) => matches(o, g.module)) }))
        .filter((g) => g.options.length > 0)
);
const hasResults = computed(() => visibleGroups.value.length > 0);

const onInput = () => { selected.value = ''; open.value = true; };
const choose = (o) => { selected.value = o.value; text.value = o.label; open.value = false; };
const onBlurClose = () => {
    open.value = false;
    if (!selected.value && text.value) { text.value = ''; query.value = ''; }
};
</script>

<template>
  <div class="relative" v-click-outside-fallback>
    <input type="hidden" :name="name" :value="selected">
    <input
      type="text"
      class="form-control w-full ss-input"
      autocomplete="off"
      :placeholder="placeholder"
      v-model="text"
      @focus="open = true"
      @input="query = text; onInput()"
    >
    <div
      v-show="open"
      class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto ss-dropdown"
    >
      <div v-for="g in groups" :key="g.module" v-show="visibleGroups.some((vg) => vg.module === g.module)" class="ss-group">
        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase bg-gray-50 sticky top-0">{{ g.module }}</div>
        <button
          v-for="o in g.options"
          :key="o.value"
          type="button"
          v-show="matches(o, g.module)"
          class="w-full text-left px-3 py-2 pl-6 text-sm text-gray-700 hover:bg-blue-50 transition-colors ss-option"
          :class="{ 'bg-blue-50 text-blue-700 font-medium': selected === o.value }"
          @click="choose(o)"
        >{{ o.label }}</button>
      </div>
      <div v-show="!hasResults" class="px-3 py-2 text-sm text-gray-400 ss-no-results">無符合結果</div>
    </div>
  </div>
</template>
```

> 「點 component 外面關閉」在真實頁用 document click 處理;單元測試聚焦過濾/選取/無結果。實作 `@focus`/`onBlurClose` 已涵蓋鍵盤離開;document 級關閉在 Task 1.2 entry 內接上(見下),避免把全域監聽寫進元件。移除 template 中 `v-click-outside-fallback`(僅示意)——實際以 entry 的 outside-click 收斂。

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/components/SearchableSelect.test.js`
Expected: PASS（4 個）。

- [ ] **Step 5: Commit**
```bash
git add resources/js/components/SearchableSelect.vue resources/js/components/SearchableSelect.test.js
git commit -m "feat(js): SearchableSelect.vue 元件 + 測試"
```

### Task 1.2: 接上 `searchable-select` 島(entry + Blade)

**Files:**
- Modify: `resources/js/components/searchable-select.js`(改為掛載器)
- Modify: `resources/views/components/searchable-select.blade.php`(掛載點 + `@json`)
- Delete(本任務結束):舊命令式邏輯已不在 `.js`

**Interfaces:**
- Consumes: `SearchableSelect.vue`(Task 1.1)、`mountIsland`(Task 0.5)。
- Blade 把 `$permissions`(Eloquent group)整理成 `groups`(`{ module, options:[{value,label,search}] }`)JSON 後以 props 傳入。

- [ ] **Step 1: 改寫 Blade 掛載點**

把 `resources/views/components/searchable-select.blade.php` 改為:在 `@php` 區把 `$permissions` 轉成 `$ssGroups` 陣列(對應原本 `data-search`/`data-label`/`data-value` 的計算:`label = description ?? name`、`search = mb_strtolower(label . ' ' . moduleLabel . ' ' . name)`、`moduleLabel = explode(' - ', firstDescription)[0] ?? module`),再渲染掛載點。每個元件實例需唯一 id(用 `Str::uuid()` 或 `uniqid`):
```blade
@props(['name', 'value' => '', 'permissions', 'placeholder' => '搜尋或選擇頁面...'])

@php
    $ssId = 'ss-' . \Illuminate\Support\Str::random(8);
    $ssGroups = collect($permissions)->map(function ($modulePermissions, $module) {
        $first = $modulePermissions->first();
        $moduleLabel = $first->description ? explode(' - ', $first->description)[0] : $module;
        return [
            'module' => $moduleLabel,
            'options' => $modulePermissions->map(function ($p) use ($moduleLabel) {
                $label = $p->description ?? $p->name;
                return [
                    'value' => $p->name,
                    'label' => $label,
                    'search' => mb_strtolower($label . ' ' . $moduleLabel . ' ' . $p->name),
                ];
            })->values(),
        ];
    })->values();
@endphp

<div
    id="{{ $ssId }}"
    data-props="@json(['name' => $name, 'value' => $value, 'placeholder' => $placeholder, 'groups' => $ssGroups])"
></div>

@once
    @push('scripts')
        @vite('resources/js/components/searchable-select.js')
    @endpush
@endonce
```

> 注意:`@once` 只 push 一次 entry,但頁面可能有多個 `<x-searchable-select>`。entry 需掃描「所有」掛載點而非單一 id(下一步處理)。因此掛載點改用 class `data-searchable-select-island` 較穩;把 `id="{{ $ssId }}"` 換成 `class="ss-island"` 並保留唯一性非必要。最終 Blade 用:
```blade
<div class="ss-island" data-props="@json([...])"></div>
```

- [ ] **Step 2: 改寫 entry 為掛載器(掃描所有島 + outside-click 收斂)**
```js
// resources/js/components/searchable-select.js
import { createApp } from 'vue';
import SearchableSelect from './SearchableSelect.vue';

document.querySelectorAll('.ss-island').forEach((el) => {
    let props = {};
    try { props = JSON.parse(el.dataset.props); } catch { props = {}; }
    createApp(SearchableSelect, props).mount(el);
});
```

> outside-click 關閉:在 `SearchableSelect.vue` 的 `onMounted` 加 `document.addEventListener('click', handler)`(判斷 `event.target` 是否在 `$el` 內,否則 `onBlurClose()`),`onBeforeUnmount` 移除。把這段補進元件並在 Task 1.1 測試外不破壞既有測試(jsdom 下 document click 不影響既有斷言)。

- [ ] **Step 3: 補元件 outside-click(回到 `SearchableSelect.vue`)**

在 `<script setup>` 末加:
```js
import { onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
let rootEl = null;
const onDocClick = (e) => { if (rootEl && !rootEl.contains(e.target)) { onBlurClose(); } };
onMounted(() => { rootEl = getCurrentInstance()?.proxy?.$el; document.addEventListener('click', onDocClick); });
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
```

- [ ] **Step 4: 跑既有測試確認仍綠**

Run: `docker compose exec backend-api npm run test -- resources/js/components/SearchableSelect.test.js`
Expected: PASS（4 個,outside-click 不影響）。

- [ ] **Step 5: build + 人工回歸**

Run: `docker compose exec backend-api npm run build`
人工:開 `roles` 新增 / 編輯頁(`resources/views/admin/roles/_form.blade.php` 用到此元件),確認搜尋、選取、送出 `default_route` 正常。

- [ ] **Step 6: Commit**
```bash
git add resources/js/components/searchable-select.js resources/js/components/SearchableSelect.vue resources/views/components/searchable-select.blade.php
git commit -m "refactor(js): searchable-select 改為 Vue 島嶼"
```

### Task 1.3: `GradeWeightField.vue` + 測試(grades/form)

來源:`resources/js/grades/form.js`(102 行)。
行為:`weight` 變更時 → 清樣式 → 驗 `< 1` → 打 `/grades/check-weight`(帶 `weight`、`exclude_id`)→ 重複時標紅衝突列、停用送出鈕並顯示錯誤;否則依權重在既有列表中插入「預覽列」(編輯模式移動既有列、建立模式插入 preview);`name` 變更同步預覽 label。

**Files:**
- Create: `resources/js/grades/GradeWeightField.vue`
- Test: `resources/js/grades/GradeWeightField.test.js`

**Interfaces:**
- Props: `excludeId: number|null`(編輯時的當前 grade id;null=建立)、`grades: Array<{ id, name, weight }>`(現有版本,依 weight 排序)、`checkUrl: string`(預設 `/grades/check-weight`)。
- 行為:組件自管 `weight`、`name`、`error`、`submitDisabled`、預覽列表;對外渲染權重輸入、名稱輸入、即時排序預覽清單與錯誤訊息;`submitDisabled` 透過對表單 submit 鈕 `:disabled` 綁定(由父層 slot 或在元件內含整個欄位區塊)。
- Consumes: `http`(`@/lib/http`)。

- [ ] **Step 1: 寫失敗測試(mock http)**
```js
// resources/js/grades/GradeWeightField.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import GradeWeightField from '@/grades/GradeWeightField.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn() } }));

const grades = [
    { id: 1, name: '旗艦版', weight: 30 },
    { id: 2, name: '標準版', weight: 20 },
    { id: 3, name: '入門版', weight: 10 },
];

beforeEach(() => { http.get.mockReset(); });

describe('GradeWeightField', () => {
    it('權重小於 1 顯示錯誤並停用送出', async () => {
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#weight').setValue('0');
        await w.get('#weight').trigger('change');
        await flushPromises();
        expect(w.get('#weight-error').text()).toContain('最低為 1');
        expect(http.get).not.toHaveBeenCalled();
    });

    it('重複權重顯示「請確認版本權重」並標紅衝突列', async () => {
        http.get.mockResolvedValue({ data: { duplicate: true, conflicting_grade: { id: 2 }, grades } });
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#weight').setValue('20');
        await w.get('#weight').trigger('change');
        await flushPromises();
        expect(w.get('#weight-error').text()).toContain('請確認版本權重');
        expect(w.get('.weight-row[data-id="2"]').classes()).toContain('text-red-600');
    });

    it('合法權重插入預覽列(建立模式)', async () => {
        http.get.mockResolvedValue({ data: { duplicate: false, grades } });
        const w = mount(GradeWeightField, { props: { excludeId: null, grades, checkUrl: '/grades/check-weight' } });
        await w.get('#name').setValue('進階版');
        await w.get('#weight').setValue('25');
        await w.get('#weight').trigger('change');
        await flushPromises();
        const preview = w.get('.weight-preview');
        expect(preview.text()).toContain('進階版');
        expect(preview.text()).toContain('25');
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/grades/GradeWeightField.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `GradeWeightField.vue`**

需保留來源所有行為(`resources/js/grades/form.js:1-102`):
- `weight` `change`:reset 樣式 → 空值 return → `<1` 設錯誤並停用送出 → `http.get(checkUrl, { params: { weight, exclude_id: excludeId || undefined } })`。
- `duplicate`:錯誤「請確認版本權重」,並對 `grades` 中 `conflicting_grade.id` 那列標紅(`text-red-600 font-semibold`)。
- 非重複:依 `data.grades` 找第一個 weight < 輸入值的列作為插入點;`excludeId`(編輯)移動既有列並標 `text-blue-600 font-medium`;建立模式插入 `.weight-preview`。
- `name` `input`:同步預覽/當前列的 label;空值用「（設定位置）」。
- `submitDisabled` 經 `:disabled` 套在送出鈕(元件內渲染整個欄位 + 列表 + 送出鈕,或用 `defineExpose` 給父層;此處採「元件含列表與錯誤訊息」,送出鈕仍由 Blade 表單持有 → 元件 emit `update:disabled`,Blade 不易接,故改為元件渲染列表預覽與錯誤,送出鈕停用以 `document.querySelector` 在 entry 綁定)。
```vue
<script setup>
import { ref, computed } from 'vue';
import http from '@/lib/http';

const props = defineProps({
    excludeId: { type: [Number, null], default: null },
    grades: { type: Array, default: () => [] },
    checkUrl: { type: String, default: '/grades/check-weight' },
});
const emit = defineEmits(['update:disabled']);

const weight = ref('');
const name = ref('');
const error = ref('');
const conflictId = ref(null);
const isEdit = computed(() => props.excludeId != null);

const setDisabled = (v) => emit('update:disabled', v);

const previewLabel = computed(() => name.value.trim() || '（設定位置）');

// 計算渲染用的列表:base grades + 預覽插入,依 weight 由大到小
const rows = computed(() => {
    const w = parseInt(weight.value);
    const base = props.grades.map((g) => ({ ...g, preview: false }));
    if (!weight.value || Number.isNaN(w) || w < 1 || error.value) {
        return base;
    }
    if (isEdit.value) {
        return base
            .map((g) => (g.id === props.excludeId ? { ...g, name: previewLabel.value, weight: w, current: true } : g))
            .sort((a, b) => b.weight - a.weight);
    }
    const inserted = [...base, { id: '__preview__', name: previewLabel.value, weight: w, preview: true }];
    return inserted.sort((a, b) => b.weight - a.weight);
});

const onWeightChange = async () => {
    error.value = '';
    conflictId.value = null;
    setDisabled(false);
    const val = weight.value.trim();
    if (!val) { return; }
    if (parseInt(val) < 1) { error.value = '版本權重最低為 1'; setDisabled(true); return; }
    let data;
    try {
        ({ data } = await http.get(props.checkUrl, {
            params: { weight: val, exclude_id: props.excludeId || undefined },
        }));
    } catch { return; }
    if (data.duplicate) {
        error.value = '請確認版本權重';
        conflictId.value = data.conflicting_grade.id;
        setDisabled(true);
    }
};
</script>

<template>
  <div>
    <input id="name" v-model="name" type="text" class="form-control" placeholder="版本名稱">
    <input id="weight" v-model="weight" type="number" class="form-control" @change="onWeightChange">
    <p id="weight-error" v-show="error" class="form-error">{{ error }}</p>
    <div id="weight-list">
      <div
        v-for="r in rows"
        :key="r.id"
        class="flex justify-between weight-row"
        :class="{
          'text-red-600 font-semibold': r.id === conflictId,
          'text-blue-600 font-medium weight-preview': r.preview || r.current,
        }"
        :data-id="r.id"
      >
        <span>{{ r.name }}</span><span>{{ r.weight }}</span>
      </div>
    </div>
  </div>
</template>
```

> 上面把「重複時整列標紅」用 `r.id === conflictId`、預覽列加 `weight-preview` class 對齊測試。送出鈕停用改由元件 emit `update:disabled`,entry 接到後操作 Blade 既有送出鈕(下一任務)。測試對 emit 可加一條:`expect(w.emitted('update:disabled').at(-1)).toEqual([true])`(重複/小於 1 時)。

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/grades/GradeWeightField.test.js`
Expected: PASS（3 個）。

- [ ] **Step 5: Commit**
```bash
git add resources/js/grades/GradeWeightField.vue resources/js/grades/GradeWeightField.test.js
git commit -m "feat(js): GradeWeightField.vue + 測試"
```

### Task 1.4: 接上 grades/form 島(entry + Blade)

**Files:**
- Modify: `resources/js/grades/form.js`
- Modify: `resources/views/admin/grades/create.blade.php`、`resources/views/admin/grades/edit.blade.php`(共用的 `_form` 區塊;確認實際表單 partial 後改該檔)

**Interfaces:**
- Consumes: `GradeWeightField.vue`、`mountIsland`。
- Blade 把現有版本清單與 `excludeId` 整成 props;送出鈕停用接 `update:disabled`。

- [ ] **Step 1: 確認表單 partial**

Run: `grep -rn "weight\|grade-form\|@vite('resources/js/grades/form" resources/views/admin/grades/`
依結果決定掛載點要放在 `create.blade.php` / `edit.blade.php` / 共用 partial。

- [ ] **Step 2: Blade 掛載點 + props**

把現有 `#weight`/`#name`/`#weight-list` 區塊換成掛載點(保留外層表單與送出鈕):
```blade
<div
    id="grade-weight-field"
    data-props="@json([
        'excludeId' => $grade->id ?? null,
        'grades' => $grades->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'weight' => $g->weight])->values(),
        'checkUrl' => route('grades.check-weight') ?? '/grades/check-weight',
    ])"
></div>
@vite('resources/js/grades/form.js')
```
(若無 `grades.check-weight` named route,用字面 `'/grades/check-weight'`。)

- [ ] **Step 3: entry 掛載 + 接 disabled**
```js
// resources/js/grades/form.js
import mountIsland from '@/lib/mountIsland';
import GradeWeightField from './GradeWeightField.vue';

const submitBtn = document.querySelector('#grade-weight-field')
    ?.closest('form')?.querySelector('button[type="submit"]');

mountIsland('grade-weight-field', GradeWeightField, {
    'onUpdate:disabled': (disabled) => {
        if (!submitBtn) { return; }
        submitBtn.disabled = disabled;
        submitBtn.classList.toggle('opacity-50', disabled);
        submitBtn.classList.toggle('cursor-not-allowed', disabled);
    },
});
```

- [ ] **Step 4: 測試 + build + 人工回歸**

Run: `docker compose exec backend-api npm run test -- resources/js/grades/GradeWeightField.test.js && docker compose exec backend-api npm run build`
人工:grades 建立 / 編輯頁:輸入重複權重→紅 + 鈕停用;合法權重→預覽插入正確位置;名稱即時同步。

- [ ] **Step 5: Commit**
```bash
git add resources/js/grades/form.js resources/views/admin/grades/
git commit -m "refactor(js): grades/form 改為 Vue 島嶼"
```

---

## M2 — 中小型頁 + 共用 modal / flash

> 先做共用 composable / 元件(deleteModal、flash、toggle 確認 modal),再逐頁接上。

### Task 2.1: `useFlash` composable + 測試

來源:`resources/js/utils/flash.js`(19 行)。`autoDismissFlashes`、`showFlash` 兩函式;插入 `.flash-area`、5 秒淡出。**保留全域 DOM 行為**(島外頁仍用)。

**Files:**
- Create: `resources/js/composables/useFlash.js`
- Test: `resources/js/composables/useFlash.test.js`

**Interfaces:**
- Produces: `useFlash(): { showFlash(type, message), autoDismissFlashes(selector?, delay?) }`(行為等同來源;`showFlash` 建立 `.flash flash-${type} flash-message` 並 prepend 到 `.flash-area`,5 秒後淡出移除)。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/composables/useFlash.test.js
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { useFlash } from '@/composables/useFlash';

beforeEach(() => { document.body.innerHTML = '<div class="flash-area"></div>'; vi.useFakeTimers(); });

describe('useFlash', () => {
    it('showFlash 插入訊息到 flash-area', () => {
        useFlash().showFlash('success', '已儲存');
        const el = document.querySelector('.flash-area .flash-message');
        expect(el).not.toBeNull();
        expect(el.textContent).toBe('已儲存');
        expect(el.className).toContain('flash-success');
    });
    it('5 秒後淡出並移除', () => {
        useFlash().showFlash('error', '失敗');
        vi.advanceTimersByTime(5000 + 600);
        expect(document.querySelector('.flash-message')).toBeNull();
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/composables/useFlash.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作**(把 `flash.js` 內容包成 composable,行為不變)
```js
// resources/js/composables/useFlash.js
function fadeAndRemove(el) {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.5s';
    setTimeout(() => el.remove(), 500);
}

export function useFlash() {
    const showFlash = (type, message) => {
        const el = document.createElement('div');
        el.className = `flash flash-${type} flash-message`;
        el.textContent = message;
        document.querySelector('.flash-area')?.prepend(el);
        setTimeout(() => fadeAndRemove(el), 5000);
    };
    const autoDismissFlashes = (selector = '.flash-message', delay = 5000) => {
        document.querySelectorAll(selector).forEach((el) => setTimeout(() => fadeAndRemove(el), delay));
    };
    return { showFlash, autoDismissFlashes };
}
```

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/composables/useFlash.test.js`
Expected: PASS。

- [ ] **Step 5: Commit**
```bash
git add resources/js/composables/useFlash.js resources/js/composables/useFlash.test.js
git commit -m "feat(js): useFlash composable + 測試"
```

> 注意:`resources/js/utils/flash.js` 暫時保留(尚未遷移的島外 entry 仍 import 它)。每當某 entry 改用 `useFlash` 就移除該 import;全部島外頁遷移完(M2 結束)後在 Task 2.9 刪除 `utils/flash.js`。

### Task 2.2: `ConfirmModal.vue` + `useConfirmModal` + 測試

來源:`resources/js/utils/deleteModal.js`(66 行,axios DELETE + 確認 modal + 移除列 + flash)與 `grades/index.js` 的 toggle 確認 modal(55 行)共用同型互動。抽成一個泛用確認 modal。

**Files:**
- Create: `resources/js/components/ConfirmModal.vue`
- Create: `resources/js/composables/useConfirmModal.js`
- Test: `resources/js/components/ConfirmModal.test.js`

**Interfaces:**
- `ConfirmModal.vue` props:`open: boolean`、`title: string`、`name: string`(顯示的目標名稱)、`actionLabel: string`(預設「確認」)、`busy: boolean`。emits:`confirm`、`cancel`。Esc 與點 overlay 背景觸發 `cancel`。
- 用 `v-if`/`v-show` 控制顯示(取代 `hidden` class hook)。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/components/ConfirmModal.test.js
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ConfirmModal from '@/components/ConfirmModal.vue';

const factory = (props = {}) => mount(ConfirmModal, {
    props: { open: true, title: '刪除', name: '測試項目', actionLabel: '確認刪除', busy: false, ...props },
    attachTo: document.body,
});

describe('ConfirmModal', () => {
    it('open 為 false 時不顯示內容', () => {
        const w = factory({ open: false });
        expect(w.find('.modal-panel').exists()).toBe(false);
    });
    it('顯示 name 與 actionLabel', () => {
        const w = factory();
        expect(w.text()).toContain('測試項目');
        expect(w.text()).toContain('確認刪除');
    });
    it('點確認鈕 emit confirm', async () => {
        const w = factory();
        await w.get('[data-confirm]').trigger('click');
        expect(w.emitted('confirm')).toBeTruthy();
    });
    it('點取消鈕 emit cancel', async () => {
        const w = factory();
        await w.get('[data-cancel]').trigger('click');
        expect(w.emitted('cancel')).toBeTruthy();
    });
    it('busy 時確認鈕停用', () => {
        const w = factory({ busy: true });
        expect(w.get('[data-confirm]').attributes('disabled')).toBeDefined();
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/components/ConfirmModal.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `ConfirmModal.vue`**(沿用 `.modal-overlay`/`.modal-panel`/`.modal-actions` 設計類)
```vue
<script setup>
import { watch, onBeforeUnmount } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '確認' },
    name: { type: String, default: '' },
    actionLabel: { type: String, default: '確認' },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

const onEsc = (e) => { if (e.key === 'Escape') { emit('cancel'); } };
watch(() => props.open, (open) => {
    if (open) { document.addEventListener('keydown', onEsc); }
    else { document.removeEventListener('keydown', onEsc); }
});
onBeforeUnmount(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
  <div v-if="open" class="modal-overlay" @click.self="emit('cancel')">
    <div class="modal-panel">
      <h3 class="text-lg font-medium mb-2">{{ title }}</h3>
      <p class="mb-4 text-gray-600">{{ name }}</p>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" data-cancel @click="emit('cancel')">取消</button>
        <button type="button" class="btn-primary" data-confirm :disabled="busy" @click="emit('confirm')">
          {{ busy ? '處理中…' : actionLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 4: 實作 `useConfirmModal.js`**(管理 open/busy/target 狀態,供列表頁島使用)
```js
// resources/js/composables/useConfirmModal.js
import { ref } from 'vue';

export function useConfirmModal() {
    const open = ref(false);
    const busy = ref(false);
    const target = ref(null);
    const show = (t) => { target.value = t; open.value = true; };
    const close = () => { open.value = false; busy.value = false; target.value = null; };
    return { open, busy, target, show, close };
}
```

- [ ] **Step 5: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/components/ConfirmModal.test.js`
Expected: PASS（5 個）。

- [ ] **Step 6: Commit**
```bash
git add resources/js/components/ConfirmModal.vue resources/js/composables/useConfirmModal.js resources/js/components/ConfirmModal.test.js
git commit -m "feat(js): ConfirmModal.vue + useConfirmModal + 測試"
```

### Task 2.3: 列表頁刪除島 `DeleteListItem`(users / roles / addons)

來源:`resources/js/utils/deleteModal.js`(66)+ `users/index.js`、`roles/index.js`、`addons/index.js`(後兩者另含 `autoDismissFlashes`、per-page select submit)。
策略:做一個掛在列表頁的小 Vue 島 `RowDeleteController.vue`,接管「`.delete-btn` 點擊 → 確認 modal → axios DELETE → 移除該列 → flash」。per-page select 與 `autoDismissFlashes` 屬頁面雜項,保留在 entry(非元件)即可。

**Files:**
- Create: `resources/js/components/RowDeleteController.vue`
- Test: `resources/js/components/RowDeleteController.test.js`
- Modify: `resources/js/users/index.js`、`resources/js/roles/index.js`、`resources/js/addons/index.js`
- Modify 對應 Blade:`resources/views/admin/users/index.blade.php`、`roles/index.blade.php`、`addons/index.blade.php`(移除舊 `#delete-modal` 標記,改放 `RowDeleteController` 掛載點;`.delete-btn` 保留 `data-url`/`data-name`,因為列仍由 Blade 渲染)。

**Interfaces:**
- `RowDeleteController.vue` props:`title`(預設「確認刪除」)、`actionLabel`(預設「確認刪除」)。掛載後:`onMounted` 對 `document` 上所有 `.delete-btn` 綁 click(讀 `data-url`/`data-name`),用 `useConfirmModal` 開 modal;確認時 `http.delete(url)` → 成功移除 `[data-url="url"]` 最近的 `<tr>` 並 `useFlash().showFlash('success', message)`;失敗顯示錯誤 flash。內含 `<ConfirmModal>`。
- Consumes:`http`、`useConfirmModal`、`useFlash`、`ConfirmModal`。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/components/RowDeleteController.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import RowDeleteController from '@/components/RowDeleteController.vue';

vi.mock('@/lib/http', () => ({ default: { delete: vi.fn() } }));

beforeEach(() => {
    http.delete.mockReset();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <table><tbody>
          <tr><td><button class="delete-btn" data-url="/users/5" data-name="Amy">刪除</button></td></tr>
        </tbody></table>
        <div id="row-delete"></div>`;
});

describe('RowDeleteController', () => {
    it('點 delete-btn 開 modal 顯示名稱', async () => {
        const w = mount(RowDeleteController, { attachTo: '#row-delete' });
        await document.querySelector('.delete-btn').click();
        await flushPromises();
        expect(w.text()).toContain('Amy');
    });

    it('確認後 DELETE 成功移除該列', async () => {
        http.delete.mockResolvedValue({ data: { message: '已刪除' } });
        const w = mount(RowDeleteController, { attachTo: '#row-delete' });
        await document.querySelector('.delete-btn').click();
        await flushPromises();
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        expect(http.delete).toHaveBeenCalledWith('/users/5');
        expect(document.querySelector('tr')).toBeNull();
        expect(document.querySelector('.flash-area').textContent).toContain('已刪除');
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/components/RowDeleteController.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `RowDeleteController.vue`**
```vue
<script setup>
import { onMounted } from 'vue';
import http from '@/lib/http';
import ConfirmModal from '@/components/ConfirmModal.vue';
import { useConfirmModal } from '@/composables/useConfirmModal';
import { useFlash } from '@/composables/useFlash';

defineProps({
    title: { type: String, default: '確認刪除' },
    actionLabel: { type: String, default: '確認刪除' },
});

const { open, busy, target, show, close } = useConfirmModal();
const { showFlash } = useFlash();

onMounted(() => {
    document.querySelectorAll('.delete-btn').forEach((btn) => {
        btn.addEventListener('click', () => show({ url: btn.dataset.url, name: btn.dataset.name }));
    });
});

const confirm = async () => {
    const url = target.value?.url;
    if (!url) { return; }
    busy.value = true;
    try {
        const res = await http.delete(url);
        document.querySelector(`[data-url="${url}"]`)?.closest('tr')?.remove();
        showFlash('success', res.data?.message ?? '已成功刪除');
    } catch (err) {
        showFlash('error', err.response?.data?.message ?? '刪除失敗，請稍後再試');
    } finally {
        close();
    }
};
</script>

<template>
  <ConfirmModal
    :open="open"
    :title="title"
    :name="target?.name ?? ''"
    :action-label="actionLabel"
    :busy="busy"
    @confirm="confirm"
    @cancel="close"
  />
</template>
```

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/components/RowDeleteController.test.js`
Expected: PASS（2 個）。

- [ ] **Step 5: 改三個 entry + Blade**

Blade(三頁):移除舊的 `#delete-modal` overlay 標記,加掛載點 `<div id="row-delete"></div>`(`.delete-btn`、`.flash-area` 保留)。
entry 範例(`users/index.js`):
```js
// resources/js/users/index.js
import mountIsland from '@/lib/mountIsland';
import RowDeleteController from '@/components/RowDeleteController.vue';
import { useFlash } from '@/composables/useFlash';

useFlash().autoDismissFlashes();
mountIsland('row-delete', RowDeleteController);
```
`addons/index.js` 另加 per-page select:
```js
document.getElementById('per-page-select')?.addEventListener('change', () => {
    document.getElementById('per-page-form').submit();
});
```
`roles/index.js` 比照 `users/index.js`。

- [ ] **Step 6: build + 人工回歸 + Commit**

Run: `docker compose exec backend-api npm run build`
人工:users / roles / addons 列表頁刪除流程(確認、取消、Esc、點背景、成功移除列 + flash、失敗 flash)。
```bash
git add resources/js/components/RowDeleteController.* resources/js/users/index.js resources/js/roles/index.js resources/js/addons/index.js resources/views/admin/users/index.blade.php resources/views/admin/roles/index.blade.php resources/views/admin/addons/index.blade.php
git commit -m "refactor(js): users/roles/addons 刪除改用 Vue 確認 modal 島"
```

### Task 2.4: grades/index toggle 島

來源:`resources/js/grades/index.js`(55,toggle 啟用/停用確認 + `axios.patch` + 切換開關樣式 + flash)。

**Files:**
- Create: `resources/js/grades/GradeToggleController.vue`
- Test: `resources/js/grades/GradeToggleController.test.js`
- Modify: `resources/js/grades/index.js`、`resources/views/admin/grades/index.blade.php`

**Interfaces:**
- 掛載後對 `.toggle-btn`(`data-active`、`data-name`、`data-url`)綁 click → `ConfirmModal`(動作詞依 `data-active`:啟用/停用)→ 確認 `http.patch(url)` → 切換該鈕的 `data-active` 與開關樣式(`bg-green-500`/`bg-gray-300`、內層 `span` 的 `translate-x-6`/`translate-x-1`)、`title` → flash「版本狀態已更新」。
- Consumes:`http`、`useConfirmModal`、`useFlash`、`ConfirmModal`。

- [ ] **Step 1: 寫失敗測試**
```js
// resources/js/grades/GradeToggleController.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import GradeToggleController from '@/grades/GradeToggleController.vue';

vi.mock('@/lib/http', () => ({ default: { patch: vi.fn() } }));

beforeEach(() => {
    http.patch.mockReset();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <button class="toggle-btn bg-green-500" data-active="1" data-name="旗艦版" data-url="/grades/1/toggle">
          <span class="translate-x-6"></span>
        </button>
        <div id="grade-toggle"></div>`;
});

describe('GradeToggleController', () => {
    it('停用後切換鈕樣式 + flash', async () => {
        http.patch.mockResolvedValue({ data: {} });
        const w = mount(GradeToggleController, { attachTo: '#grade-toggle' });
        await document.querySelector('.toggle-btn').click();
        await flushPromises();
        expect(w.text()).toContain('停用');
        await w.get('[data-confirm]').trigger('click');
        await flushPromises();
        const btn = document.querySelector('.toggle-btn');
        expect(btn.dataset.active).toBe('0');
        expect(btn.classList.contains('bg-gray-300')).toBe(true);
        expect(document.querySelector('.flash-area').textContent).toContain('版本狀態已更新');
    });
});
```

- [ ] **Step 2-4: 跑失敗 → 實作 → 跑通過**

實作 `GradeToggleController.vue`,完整 port `grades/index.js:initToggleModal` 的成功分支樣式切換與失敗 flash。
Run（前後）: `docker compose exec backend-api npm run test -- resources/js/grades/GradeToggleController.test.js`

- [ ] **Step 5: 改 entry + Blade + build + 人工回歸 + Commit**
```js
// resources/js/grades/index.js
import mountIsland from '@/lib/mountIsland';
import GradeToggleController from './GradeToggleController.vue';
import { useFlash } from '@/composables/useFlash';
useFlash().autoDismissFlashes();
mountIsland('grade-toggle', GradeToggleController);
```
Blade:移除舊 `#toggle-modal` 標記,加 `<div id="grade-toggle"></div>`。
```bash
git add resources/js/grades/GradeToggleController.* resources/js/grades/index.js resources/views/admin/grades/index.blade.php
git commit -m "refactor(js): grades/index toggle 改為 Vue 島嶼"
```

### Task 2.5: shops/index 島(cert badge modal + per-page + flash)

來源:`resources/js/shops/index.js`(35)。cert badge 點擊開 modal 顯示統編/公司名;per-page select submit;`autoDismissFlashes`。

**Files:**
- Create: `resources/js/shops/CertBadgeModal.vue`
- Test: `resources/js/shops/CertBadgeModal.test.js`
- Modify: `resources/js/shops/index.js`、`resources/views/admin/shops/index.blade.php`

**Interfaces:**
- `CertBadgeModal.vue`:`onMounted` 對 `.cert-badge`(`data-business-number`、`data-company-name`)綁 click → 開 modal 顯示;關閉鈕 / 點背景關閉。`v-if` 控制顯示。

- [ ] **Step 1-4: TDD**(測試:點 `.cert-badge` → modal 顯示對應統編與公司名;點關閉 → 隱藏)
Run: `docker compose exec backend-api npm run test -- resources/js/shops/CertBadgeModal.test.js`

- [ ] **Step 5: entry + Blade + build + 人工 + Commit**
```js
// resources/js/shops/index.js
import mountIsland from '@/lib/mountIsland';
import CertBadgeModal from './CertBadgeModal.vue';
import { useFlash } from '@/composables/useFlash';
useFlash().autoDismissFlashes();
document.getElementById('per-page-select')?.addEventListener('change', () => {
    document.getElementById('per-page-form').submit();
});
mountIsland('cert-badge-modal', CertBadgeModal);
```
```bash
git commit -m "refactor(js): shops/index cert badge 改為 Vue 島嶼"
```

### Task 2.6: shops/edit 島(admin email toggle + cert 認證 modal)

來源:`resources/js/shops/edit.js`(125)。兩塊:① email 遮罩/輸入切換(驗證失敗自動展開);② cert 認證 modal(統編 8 碼驗證 → `fetch(certRoute, POST)` → 成功填 hidden + 顯示遮罩值 + 改鈕為「完成/關閉」;失敗顯示錯誤)。`maskString` 與後端同演算法。

**Files:**
- Create: `resources/js/shops/ShopEditPanel.vue`
- Create: `resources/js/shops/maskString.js` + `resources/js/shops/maskString.test.js`(抽純函式先測)
- Test: `resources/js/shops/ShopEditPanel.test.js`
- Modify: `resources/js/shops/edit.js`、`resources/views/admin/shops/edit.blade.php`

**Interfaces:**
- Props:`certRoute: string`、`adminEmailError: boolean`(驗證失敗時 email 區預設展開)、`csrfToken: string`、初始遮罩 email 值等。
- Consumes:`http`(cert 認證改用 `http.post`,取代裸 `fetch`,測試可 mock;header 由 http 實例帶,CSRF 走 axios 既有機制或顯式帶)。

- [ ] **Step 1: 先 TDD `maskString`**
```js
// resources/js/shops/maskString.test.js
import { describe, it, expect } from 'vitest';
import { maskString } from '@/shops/maskString';
describe('maskString', () => {
    it('奇數索引換星號', () => { expect(maskString('12345678')).toBe('1*3*5*7*'); });
});
```
實作:
```js
// resources/js/shops/maskString.js
export function maskString(value) {
    return value.split('').map((c, i) => (i % 2 === 1 ? '*' : c)).join('');
}
```

- [ ] **Step 2: TDD `ShopEditPanel.vue`**(測試重點:① 點 toggle 在遮罩/輸入間切換;② `adminEmailError` 為真時初始展開;③ 統編非 8 碼顯示錯誤、不送出;④ `http.post` 成功 → 填入公司名與遮罩統編、鈕變「完成」)。mock `@/lib/http`。port `shops/edit.js` 全部行為。

- [ ] **Step 3: entry + Blade**
```js
// resources/js/shops/edit.js
import mountIsland from '@/lib/mountIsland';
import ShopEditPanel from './ShopEditPanel.vue';
mountIsland('shop-edit-panel', ShopEditPanel);
```
Blade:把 email 區與 cert modal 區換成掛載點 `<div id="shop-edit-panel" data-props="@json([...])"></div>`,hidden 欄位(`business_number`、`company_name`)仍在表單內,由元件用 `v-model` 或 ref 寫值。

- [ ] **Step 4: 測試 + build + 人工回歸**
Run: `docker compose exec backend-api npm run test -- resources/js/shops/`
人工:email 切換、驗證失敗自動展開、cert 認證成功/失敗流程、儲存後 hidden 值正確。

- [ ] **Step 5: Commit**
```bash
git commit -m "refactor(js): shops/edit 改為 Vue 島嶼"
```

### Task 2.7: addons/form 島(圖片上傳預覽)

來源:`resources/js/addons/form.js`(57)。選檔 `FileReader` 預覽、移除(設 `remove_image=1`、清空)、驗證失敗重渲染還原「已刪除」狀態、無圖時隱藏移除鈕。

**Files:**
- Create: `resources/js/addons/ImageUploadField.vue`
- Test: `resources/js/addons/ImageUploadField.test.js`
- Modify: `resources/js/addons/form.js`、`resources/views/admin/addons/_form.blade.php`

**Interfaces:**
- Props:`initialImage: string|null`、`pendingRemove: boolean`(驗證失敗重渲染時)、`inputName`(預設 `image`)、`removeFlagName`(預設 `remove_image`)。
- 內含 `<input type="file">`、預覽 `<img>`、移除鈕、hidden `remove_image`;以響應式 state 取代 `classList` 切換。

- [ ] **Step 1-4: TDD**(測試:選檔後顯示預覽 + `remove_image=0`;移除後隱藏預覽 + `remove_image=1`;`pendingRemove` 初始為已刪除狀態;無初始圖時移除鈕隱藏)。`FileReader` 在 jsdom 可用;用 `new File([...])` + 觸發 `change`。

- [ ] **Step 5: entry + Blade + build + 人工 + Commit**
```bash
git commit -m "refactor(js): addons/form 圖片上傳改為 Vue 島嶼"
```

### Task 2.8: 純雜項頁(conferences/index、layouts/admin)

**Files:**
- Modify: `resources/js/conferences/index.js`、`resources/js/layouts/admin.js`

**conferences/index.js**:只有 `autoDismissFlashes()` → 改 import `useFlash`:
- [ ] **Step 1:**
```js
import { useFlash } from '@/composables/useFlash';
useFlash().autoDismissFlashes();
```

**layouts/admin.js**(session 倒數計時器,39 行,純 UI 計時):可選擇做成 `SessionTimer.vue` 掛在 header,或保留原樣(它不讀後端、無 DOM 命令式痛點)。本計畫為「全面 Vue 化」,做成元件:
- [ ] **Step 2: TDD `SessionTimer.vue`**(props:`lifetime: number`、`loginUrl: string`;每秒遞減、格式化 `HH:MM:SS`、≤300 變紅、歸零導向 loginUrl)。用 `vi.useFakeTimers()` 測遞減與顏色切換。
- [ ] **Step 3:** entry `layouts/admin.js` 改 `mountIsland('session-timer', SessionTimer, { lifetime, loginUrl })`;Blade `admin.blade.php` 把 `#session-timer` 換成掛載點(props 由原 `<meta>` 值改成 `@json`)。
- [ ] **Step 4: build + 人工(倒數正常、5 分鐘內變紅)+ Commit**
```bash
git commit -m "refactor(js): conferences/index、layouts/admin session timer 改用 Vue/composable"
```

### Task 2.9: 移除 `utils/flash.js`、`utils/deleteModal.js`

前置:確認沒有任何 entry 還 import 它們。

- [ ] **Step 1: 確認無殘留 import**

Run: `grep -rn "utils/flash\|utils/deleteModal" resources/js`
Expected: 無結果(全部已改用 `@/composables/useFlash`、`RowDeleteController`)。

- [ ] **Step 2: 刪除**
```bash
git rm resources/js/utils/flash.js resources/js/utils/deleteModal.js
```

- [ ] **Step 3: build + 全測試**

Run: `docker compose exec backend-api npm run build && docker compose exec backend-api npm run test`
Expected: build 成功、全測試綠。

- [ ] **Step 4: Commit**
```bash
git commit -m "chore(js): 移除已被 composable 取代的 utils/flash、utils/deleteModal"
```

---

## M3 — 大型表單 / 列表

### Task 3.1: `BillsListModals.vue`(bills/index)

來源:`resources/js/bills/index.js`(240)。三個 modal:① 明細 modal(`GET /bills/{id}/detail` → 渲染明細表 + 作廢區 + 總計;`payment_status===1` 顯示匯出鈕);② 匯出(`fetch /bills/{id}/quotation` blob 下載);③ 銷帳 modal(載入可銷帳明細、勾選 → `POST /bills/{id}/writeoff` → reload);④ 編輯 modal(填現值 → `PATCH /bills/{id}` → reload)。`autoDismissFlashes`。

**Files:**
- Create: `resources/js/bills/BillsListModals.vue`
- Test: `resources/js/bills/BillsListModals.test.js`
- Modify: `resources/js/bills/index.js`、`resources/views/admin/bills/index.blade.php`

**Interfaces:**
- Props:`detailUrlTemplate`(例 `/bills/:id/detail`)等,或元件內以 `data-bill-id`/`data-bill-no` 從觸發鈕讀(列仍由 Blade 渲染)。`csrfToken`。
- Consumes:`http`、`useFlash`、`ConfirmModal`(可選)。共用 `typeLabels`/`typeBadgeClass` 常數。
- 行為對照 `bills/index.js`:
  - `.detail-btn` click(`data-bill-id`/`data-bill-no`)→ 開明細 modal、loading → `http.get('/bills/'+id+'/detail')` → 渲染 `bill` meta + active/void 明細列 + 小計/折抵/總額;`bill.payment_status===1` 顯示匯出鈕。
  - 匯出:`http.get('/bills/'+id+'/quotation', { responseType: 'blob' })` 取 `Content-Disposition` 檔名 → 觸發下載;進度用 export modal。
  - `.writeoff-btn` → 載入 `is_effective===1 && type!==4` 明細勾選 → `http.post('/bills/'+id+'/writeoff', { detail_ids })` → `window.location.reload()`。
  - `.edit-btn`(`data-payment-status`/`data-paid-at`/`data-invoice-no`)→ 表單 → `http.patch('/bills/'+id, {...})` → reload。

- [ ] **Step 1: 寫失敗測試(挑關鍵行為,mock http)**
```js
// resources/js/bills/BillsListModals.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import BillsListModals from '@/bills/BillsListModals.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));

beforeEach(() => {
    vi.clearAllMocks();
    document.body.innerHTML = `
        <div class="flash-area"></div>
        <button class="detail-btn" data-bill-id="7" data-bill-no="B-007">明細</button>
        <button class="writeoff-btn" data-bill-id="7" data-bill-no="B-007">銷帳</button>
        <div id="bills-modals"></div>`;
});

describe('BillsListModals 明細', () => {
    it('開明細載入並渲染明細與總額', async () => {
        http.get.mockResolvedValue({ data: {
            bill: { shop_name: 'A店', creator_name: 'Amy', status_label: '已付款', status_class: 'x', payment_status: 1, total_grade: 1000, total_addons: 200, discount_amount: 100, total: 1100 },
            details: [{ name: '旗艦版', type: 1, total_price: 1000, start_at: '2026-01-01', expired_at: '2026-12-31', is_effective: 1 }],
        } });
        const w = mount(BillsListModals, { attachTo: '#bills-modals' });
        await document.querySelector('.detail-btn').click();
        await flushPromises();
        expect(w.text()).toContain('旗艦版');
        expect(w.text()).toContain('1,100');
        expect(http.get).toHaveBeenCalledWith('/bills/7/detail');
    });
});

describe('BillsListModals 銷帳', () => {
    it('未勾選不送出', async () => {
        http.get.mockResolvedValue({ data: { details: [{ id: 1, name: 'x', type: 1, total_price: 1, is_effective: 1 }] } });
        const w = mount(BillsListModals, { attachTo: '#bills-modals' });
        await document.querySelector('.writeoff-btn').click();
        await flushPromises();
        await w.get('[data-writeoff-confirm]').trigger('click');
        expect(http.post).not.toHaveBeenCalled();
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/bills/BillsListModals.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `BillsListModals.vue`**

完整 port `bills/index.js:1-241` 全部四塊互動為響應式狀態(`detail`/`writeoff`/`edit`/`export` 各自 `open`/`loading`/`data`),用 `v-if`/`v-for` 渲染明細表與作廢區。匯出檔名解析沿用原本 `Content-Disposition` regex。所有 `alert(...)` 可改 `useFlash().showFlash('error', ...)`(行為等價、體驗一致)。

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/bills/BillsListModals.test.js`
Expected: PASS。

- [ ] **Step 5: entry + Blade**
```js
// resources/js/bills/index.js
import mountIsland from '@/lib/mountIsland';
import BillsListModals from './BillsListModals.vue';
import { useFlash } from '@/composables/useFlash';
useFlash().autoDismissFlashes();
mountIsland('bills-modals', BillsListModals, { csrfToken: document.querySelector('meta[name="csrf-token"]')?.content });
```
Blade:移除舊四個 modal 標記,加 `<div id="bills-modals"></div>`;`.detail-btn`/`.writeoff-btn`/`.edit-btn` 保留在列上(列由 Blade 渲染)。

- [ ] **Step 6: build + 人工回歸 + Commit**

人工:明細(含作廢區、匯出鈕條件)、匯出下載、銷帳(勾選/未勾選/reload)、編輯(reload)。
```bash
git commit -m "refactor(js): bills/index 四個 modal 改為 Vue 島嶼"
```

### Task 3.2: `BillCreateWizard.vue`(bills/create)

來源:`resources/js/bills/create.js`(637)。單頁逐步精靈:Step1 搜尋商店 → Step2 載入 shop info → Step3 切換 grade/addon 區塊 → grade 區(op 切換、版本過濾、月數、`/calculate`)→ addon 區(多列、配額數量、互斥標記、`/calculate`)→ 折抵 → 訂單摘要 → 送出(組 `details[i][...]` hidden + 驗證)。

**Files:**
- Create: `resources/js/bills/BillCreateWizard.vue`
- Create: `resources/js/bills/billMath.js` + `resources/js/bills/billMath.test.js`(抽純函式:`monthsOptions(startDate)`、`paymentTypeFromMonths(m)`、`fmt(n)`、`getSubtotal(details)`)
- Test: `resources/js/bills/BillCreateWizard.test.js`
- Modify: `resources/js/bills/create.js`、`resources/views/admin/bills/create.blade.php`

**Interfaces:**
- Props（取代 `window.billConfig`）:`shopSearchUrl`、`shopInfoUrl`、`calculateUrl`、`today`、`submitUrl`。
- Consumes:`http`、`billMath` 純函式。
- State:`step`、`selectedShopId`、`shopData`、`gradeEnabled`/`addonEnabled`、`gradeOp`、`gradeForm`、`addonRows[]`、`discount`、`paymentMethod`;`subtotal`/`total` 為 `computed`。送出時組 `details` 陣列(以隱藏 input 或直接 axios POST — 建議仍走原生表單 POST 維持後端不變:用 `v-for` 渲染 hidden inputs)。

- [ ] **Step 1: TDD 純函式 `billMath.js`**
```js
// resources/js/bills/billMath.test.js
import { describe, it, expect } from 'vitest';
import { monthsOptions, paymentTypeFromMonths, fmt } from '@/bills/billMath';

describe('billMath', () => {
    it('fmt 加千分位與 NT$', () => { expect(fmt(1234567)).toBe('NT$1,234,567'); });
    it('paymentTypeFromMonths 對照', () => {
        expect(paymentTypeFromMonths(1)).toBe(1);
        expect(paymentTypeFromMonths(3)).toBe(2);
        expect(paymentTypeFromMonths(12)).toBe(3);
        expect(paymentTypeFromMonths(24)).toBe(3);
        expect(paymentTypeFromMonths(36)).toBe(3);
        expect(paymentTypeFromMonths(5)).toBeNull();
    });
    it('monthsOptions 月初不含「月底」選項', () => {
        const opts = monthsOptions('2026-03-01');
        expect(opts.find((o) => o.v === 0)).toBeUndefined();
        expect(opts).toHaveLength(36);
    });
    it('monthsOptions 月中含「月底」', () => {
        const opts = monthsOptions('2026-03-15');
        expect(opts[0]).toEqual({ v: 0, l: '月底' });
        expect(opts).toHaveLength(37);
    });
});
```
實作 `billMath.js`:把 `create.js` 的 `fmt`、`monthsOptions`、`paymentTypeFromMonths` 原樣搬出(行為等價)。

- [ ] **Step 2: 跑純函式測試(失敗→實作→通過)**

Run: `docker compose exec backend-api npm run test -- resources/js/bills/billMath.test.js`

- [ ] **Step 3: TDD `BillCreateWizard.vue` 關鍵流程**

測試(mock http)涵蓋至少:
- 搜尋:輸入關鍵字(debounce 後)呼叫 `shopSearchUrl`,渲染候選並可點選。
- 確認商店:`shopInfoUrl` 成功 → 進 step3、顯示 shop 資訊與 pending 警告(`pending_bill_count>0`)。
- grade 計算:選 op=upgrade、選版本、月數 → `calculateUrl` → 摘要出現該列與金額。
- 折抵超過小計 → 顯示錯誤、送出被擋。
```js
// resources/js/bills/BillCreateWizard.test.js（節錄一條）
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import BillCreateWizard from '@/bills/BillCreateWizard.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const props = { shopSearchUrl: '/bills/shop-search', shopInfoUrl: '/bills/shop-info', calculateUrl: '/bills/calculate', today: '2026-06-18', submitUrl: '/bills' };
beforeEach(() => vi.clearAllMocks());

describe('BillCreateWizard 搜尋', () => {
    it('搜尋呼叫 API 並渲染候選', async () => {
        http.get.mockResolvedValue({ data: { shops: [{ id: 1, label: 'A 店' }] } });
        const w = mount(BillCreateWizard, { props });
        await w.get('#shop-keyword').setValue('A');
        await w.get('#shop-search-btn').trigger('click');
        await flushPromises();
        expect(w.text()).toContain('A 店');
    });
});
```

- [ ] **Step 4: 實作 `BillCreateWizard.vue`**

完整 port `create.js:1-637` 全部行為(逐塊對照,務必保留):搜尋 debounce 300ms、shop info 載入與失敗 fallback(回 step1 + alert/flash)、grade op 日期約束(upgrade=today 可改、renew/downgrade=expired_at+1 鎖定)、版本過濾與排序、月數選項、`type` 與 `current_grade_price`(升級補差額 isUpgradeDiff)、grade overlap 警告、addon 多列(配額數量欄、互斥 `markSelectedAddons`、已包含/已購買後綴)、addon 計算、折抵啟用/上限驗證/info、訂單摘要列與小計/折抵/總額、送出組 `details[i][...]` hidden + 折抵欄位 + 商店/付款方式,submit 前驗證(折抵>小計、未選商店)。`alert(...)` 改 `useFlash().showFlash('error', ...)` 或保留(擇一,計畫採 useFlash)。

- [ ] **Step 5: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/bills/BillCreateWizard.test.js`
Expected: PASS。

- [ ] **Step 6: entry + Blade**
```js
// resources/js/bills/create.js
import mountIsland from '@/lib/mountIsland';
import BillCreateWizard from './BillCreateWizard.vue';
mountIsland('bill-create-wizard', BillCreateWizard);
```
Blade `create.blade.php`:把整個精靈區塊換成 `<form id="bill-form" ...>` 內一個 `<div id="bill-create-wizard" data-props="@json([...])"></div>`(props 取代 `window.billConfig`);hidden 欄位由元件 `v-for` 產生。保留 `<form>` 的 action/method/`@csrf`(維持後端 POST 不變)。

- [ ] **Step 7: build + 人工全流程回歸 + Commit**

人工:完整走一遍建立帳單(搜尋→確認→grade 升級/續約/降級→addon 多列含配額→折抵→送出),對照舊版金額與送出 payload 一致。
```bash
git commit -m "refactor(js): bills/create 精靈改為 Vue 島嶼"
```

---

## M4 — 聊天頁(壓軸,最高風險)

> 先把 `bootstrap.js` 的 Echo / badge 收斂成 composable,再改聊天頁本體。聊天頁狀態最複雜,務必逐項對照 `chats/index.js` 行為並補人工測試清單。

### Task 4.1: `useEcho` + `useChatBadge` composable + 測試

來源:`resources/js/bootstrap.js`(55)。axios 全域、Echo/Reverb 設定(`<meta user-id>` + `import.meta.env.VITE_REVERB_*`)、`chat.user.{id}` 訂閱轉 `chat:message` CustomEvent、`refreshChatBadge`。

**Files:**
- Create: `resources/js/composables/useEcho.js`
- Create: `resources/js/composables/useChatBadge.js`
- Test: `resources/js/composables/useChatBadge.test.js`
- Modify: `resources/js/bootstrap.js`(改用 composable;保留全域 `window.Echo`/`window.axios` 以相容尚未遷移情境,但 `refreshChatBadge` 改委派 `useChatBadge`)

**Interfaces:**
- `useEcho(): { echo: Echo|null }` — 回傳已建立的 `window.Echo`(或 null);不重複建立。
- `useChatBadge(): { refresh(): Promise<void> }` — `http.get('/chats/unread-count')` → 更新 `#chat-unread-badge` 文字與 `hidden`。

- [ ] **Step 1: TDD `useChatBadge`**(mock http;`#chat-unread-badge` 存在時更新文字、>0 顯示、<=0 hidden)
```js
// resources/js/composables/useChatBadge.test.js
import { describe, it, expect, vi, beforeEach } from 'vitest';
import http from '@/lib/http';
import { useChatBadge } from '@/composables/useChatBadge';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn() } }));
beforeEach(() => {
    http.get.mockReset();
    document.body.innerHTML = '<span id="chat-unread-badge" class="hidden"></span>';
});

describe('useChatBadge', () => {
    it('未讀 > 0 顯示數字', async () => {
        http.get.mockResolvedValue({ data: { unread_count: 3 } });
        await useChatBadge().refresh();
        const b = document.getElementById('chat-unread-badge');
        expect(b.textContent).toBe('3');
        expect(b.classList.contains('hidden')).toBe(false);
    });
    it('未讀 0 隱藏', async () => {
        http.get.mockResolvedValue({ data: { unread_count: 0 } });
        await useChatBadge().refresh();
        expect(document.getElementById('chat-unread-badge').classList.contains('hidden')).toBe(true);
    });
});
```

- [ ] **Step 2-4: 失敗→實作→通過**

`useChatBadge.js`:port `bootstrap.js:13-25`。`useEcho.js`:port `bootstrap.js:27-52` 的 Echo 建立(讀 env 與 meta、Reverb 設定),回傳實例;`bootstrap.js` 改為呼叫 `useEcho()` 並把 `chat.user.{id}` 監聽轉 `chat:message`,`window.refreshChatBadge = () => useChatBadge().refresh()`。
Run: `docker compose exec backend-api npm run test -- resources/js/composables/useChatBadge.test.js`

- [ ] **Step 5: build + 人工(badge 仍會更新)+ Commit**
```bash
git commit -m "refactor(js): 抽出 useEcho / useChatBadge composable"
```

### Task 4.2: 聊天純函式抽出 + 測試

來源:`chats/index.js:42-96`(`formatDayLabel`、`formatListTime`、`initials`、`escapeHtml`、`isSameDay`、`dayKey`)。

**Files:**
- Create: `resources/js/chats/chatFormat.js`
- Test: `resources/js/chats/chatFormat.test.js`

- [ ] **Step 1: TDD**
```js
// resources/js/chats/chatFormat.test.js
import { describe, it, expect } from 'vitest';
import { initials, isSameDay, dayKey } from '@/chats/chatFormat';

describe('chatFormat', () => {
    it('initials 單名取首字', () => { expect(initials('Amy')).toBe('A'); });
    it('initials 雙名取兩首字大寫', () => { expect(initials('Amy Lee')).toBe('AL'); });
    it('initials 空字串回 ?', () => { expect(initials('')).toBe('?'); });
    it('isSameDay 同日為真', () => {
        expect(isSameDay(new Date('2026-06-18T01:00'), new Date('2026-06-18T23:00'))).toBe(true);
    });
});
```

- [ ] **Step 2-4: 失敗→實作(搬出純函式)→通過**

Run: `docker compose exec backend-api npm run test -- resources/js/chats/chatFormat.test.js`

- [ ] **Step 5: Commit**
```bash
git commit -m "feat(js): 抽出聊天格式化純函式 + 測試"
```

### Task 4.3: `ChatApp.vue` 對話列表 + 開啟對話 + 載入訊息

來源:`chats/index.js` 列表(`renderConversations`/`loadConversations`/錯誤與骨架狀態)、`openConversation`(載入訊息、骨架、失敗錯誤狀態、切換對話放棄渲染、空狀態判斷、`patch read` + badge)。

**Files:**
- Create: `resources/js/chats/ChatApp.vue`
- Test: `resources/js/chats/ChatApp.test.js`
- (entry/Blade 在 Task 4.5 接上)

**Interfaces:**
- Props:`meId: number`。Consumes:`http`、`chatFormat`、`useEcho`、`useChatBadge`、`useFlash`(可選)。
- State:`conversations[]`、`activeId`、`activeOtherId`、`activeOtherName`、`messages[]`、`onlineUsers:Set`、`listLoading`/`listError`、`threadLoading`/`threadError`、`pendingSends[]`、`renderedMessageIds:Set`、`typing`、`scrollPill`。

- [ ] **Step 1: TDD(列表 + 開啟對話)**
```js
// resources/js/chats/ChatApp.test.js（節錄）
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import http from '@/lib/http';
import ChatApp from '@/chats/ChatApp.vue';

vi.mock('@/lib/http', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn() } }));
vi.mock('@/composables/useEcho', () => ({ useEcho: () => ({ echo: null }) }));
vi.mock('@/composables/useChatBadge', () => ({ useChatBadge: () => ({ refresh: vi.fn() }) }));
beforeEach(() => vi.clearAllMocks());

describe('ChatApp 列表', () => {
    it('載入並渲染對話清單', async () => {
        http.get.mockResolvedValueOnce({ data: { conversations: [
            { id: 1, other_user: { id: 9, name: 'Bob' }, last_message: 'hi', last_message_at: null, unread_count: 2 },
        ] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.text()).toContain('Bob');
        expect(w.text()).toContain('2'); // unread badge
    });

    it('清單為空顯示空狀態', async () => {
        http.get.mockResolvedValueOnce({ data: { conversations: [] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.get('[data-convo-empty]').isVisible()).toBe(true);
    });

    it('清單載入失敗(初次)顯示可重試錯誤', async () => {
        http.get.mockRejectedValueOnce(new Error('x'));
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        expect(w.get('[data-convo-error]').isVisible()).toBe(true);
    });
});
```

- [ ] **Step 2: 跑測試確認失敗**

Run: `docker compose exec backend-api npm run test -- resources/js/chats/ChatApp.test.js`
Expected: FAIL。

- [ ] **Step 3: 實作 `ChatApp.vue`(列表 + openConversation 部分)**

完整 port:`loadConversations`(成功渲染、失敗時保留既有列表或顯示錯誤;骨架隱藏)、`renderConversations`(空狀態、未讀 badge、線上點、active 高亮、`formatListTime`)、`openConversation`(清狀態、骨架、`GET /chats/{id}/messages`、失敗→錯誤狀態不可誤當空對話、切換對話放棄渲染、reverse 後 append、空狀態判斷、`PATCH /chats/{id}/read` + badge refresh)。用響應式 `messages`/`conversations` 取代命令式 DOM。

- [ ] **Step 4: 跑測試確認通過**

Run: `docker compose exec backend-api npm run test -- resources/js/chats/ChatApp.test.js`
Expected: PASS。

- [ ] **Step 5: Commit**
```bash
git commit -m "feat(js): ChatApp.vue 列表與開啟對話 + 測試"
```

### Task 4.4: ChatApp 送訊息(樂觀送出 + 去重 + 重試)

來源:`chats/index.js:247-366`(`appendMessage` 去重與認領、`settle`、`sendBody` 樂觀送出、失敗重試)+ 訊息分組 / 日期分隔 / scroll(`isNearBottom`/`forceScrollBottom`/`scrollPill`)。

**Files:**
- Modify: `resources/js/chats/ChatApp.vue`
- Modify: `resources/js/chats/ChatApp.test.js`

**Interfaces:**
- Produces 行為:`sendBody(body)` 樂觀插入(狀態 `sending`)→ `POST /chats/{id}/messages` → 成功 `settle('sent')`、失敗 `settle('failed', retry)`;以 `renderedMessageIds` + `pendingSends` 對廣播回來的自送訊息去重認領(避免重複泡泡與重送)。

- [ ] **Step 1: 新增失敗測試**
```js
// 追加到 ChatApp.test.js
describe('ChatApp 送訊息', () => {
    it('樂觀送出顯示訊息且成功標記已送出', async () => {
        http.get.mockResolvedValue({ data: { conversations: [{ id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 } ] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        // 開對話
        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();
        http.post.mockResolvedValueOnce({ data: { message: { id: 100 } } });
        await w.get('#message-input').setValue('hello');
        await w.get('#message-form').trigger('submit');
        await flushPromises();
        expect(w.text()).toContain('hello');
        expect(http.post).toHaveBeenCalledWith('/chats/1/messages', { body: 'hello' });
    });

    it('送出失敗顯示重試', async () => {
        http.get.mockResolvedValue({ data: { conversations: [{ id: 1, other_user: { id: 9, name: 'Bob' }, last_message: '', last_message_at: null, unread_count: 0 }] } });
        const w = mount(ChatApp, { props: { meId: 1 } });
        await flushPromises();
        http.get.mockResolvedValueOnce({ data: { messages: [] } });
        await w.get('[data-convo-id="1"]').trigger('click');
        await flushPromises();
        http.post.mockRejectedValueOnce(new Error('net'));
        await w.get('#message-input').setValue('boom');
        await w.get('#message-form').trigger('submit');
        await flushPromises();
        expect(w.text()).toContain('傳送失敗');
    });
});
```

- [ ] **Step 2-4: 失敗→實作→通過**

實作 `sendBody`/`settle`/`appendMessage` 去重認領與分組 / 日期分隔 / scroll 行為(完整 port,保留註解說明的競態處理:廣播認領 vs POST 回應遺失不重送)。
Run: `docker compose exec backend-api npm run test -- resources/js/chats/ChatApp.test.js`

- [ ] **Step 5: Commit**
```bash
git commit -m "feat(js): ChatApp 樂觀送出 + 去重 + 重試"
```

### Task 4.5: ChatApp 即時(Echo)+ typing + 線上狀態 + 開新對話,接上 entry/Blade

來源:`chats/index.js:368-534`(typing whisper、presence `chat.online`、`chat:message` 事件、`userSelect` 開新對話、retry 綁定)。

**Files:**
- Modify: `resources/js/chats/ChatApp.vue`
- Modify: `resources/js/chats/index.js`
- Modify: `resources/views/admin/chats/index.blade.php`
- Modify: `resources/js/chats/ChatApp.test.js`(typing/presence 以 mock echo 注入測試)

**Interfaces:**
- `useEcho()` 提供 echo;`ChatApp` 在 `onMounted` 訂閱 `chat.online`(here/joining/leaving 更新 `onlineUsers`)、開對話時 `subscribeConversationChannel`(listenForWhisper 'typing')、`input` whisper 'typing'、監聽 window `chat:message`。Echo 為 null 時全部跳過(頁面仍可載入/送訊息)。

- [ ] **Step 1: 新增 typing/presence 測試(注入 mock echo)**

以 `vi.mock('@/composables/useEcho')` 回傳一個可控的假 echo(`join().here()/joining()/leaving()`、`private().listenForWhisper()/whisper()`),斷言:`here` 帶入線上使用者後對方對話顯示線上點;`input` 觸發 whisper。

- [ ] **Step 2-4: 失敗→實作→通過**

完整 port presence、typing、`chat:message`(本分頁去重)、`userSelect` 開新對話(`POST /chats/start` → reload 列表 → 開對話)、retry。
Run: `docker compose exec backend-api npm run test -- resources/js/chats/ChatApp.test.js`

- [ ] **Step 5: entry + Blade 掛載點**
```js
// resources/js/chats/index.js
import mountIsland from '@/lib/mountIsland';
import ChatApp from './ChatApp.vue';
mountIsland('chat-app', ChatApp, { meId: Number(document.getElementById('chat-app')?.dataset.userId) });
```
Blade `admin/chats/index.blade.php`:把整個聊天 UI(列表 / thread / 表單 / 各狀態節點)移進 `ChatApp.vue` template,Blade 只留 `<div id="chat-app" data-user-id="{{ auth()->id() }}"></div>`(`meId` 也可改 `data-props`)。`<meta name="user-id">` 與 `bootstrap.js` 的全站訂閱維持。

- [ ] **Step 6: build + 人工回歸(雙人實測)+ Commit**

人工測試清單(兩個瀏覽器 session):
- 即時收訊息(對方送、我這邊即時出現 + badge 更新)。
- 樂觀送出:送出立即顯示「傳送中」→「已送出」;廣播回來不重複泡泡。
- 送出失敗(關後端模擬)顯示「傳送失敗 + 重試」,重試成功。
- typing:對方輸入時我看到「輸入中」2 秒後消失。
- 線上狀態:對方上線/離線,列表與標頭線上點即時更新。
- 開新對話(userSelect)、未讀紅點、scroll-to-latest pill、骨架與載入失敗重試。
```bash
git commit -m "refactor(js): chats/index 改為 Vue 島嶼(ChatApp)"
```

### Task 4.6: 收尾與全量驗證

**Files:**
- Modify: `resources/js/bootstrap.js`(確認已用 composable;`window.Echo`/`window.axios` 視需要保留)
- 確認 `resources/js/app.js` 仍 `import './bootstrap'`

- [ ] **Step 1: 全量測試**

Run: `docker compose exec backend-api npm run test`
Expected: 全部 `.test.js` PASS。

- [ ] **Step 2: 全量 build**

Run: `docker compose exec backend-api npm run build`
Expected: 成功,無未解析 import。

- [ ] **Step 3: 殘留掃描**

Run: `grep -rn "addEventListener('DOMContentLoaded'\|querySelectorAll\|classList.add('hidden')" resources/js --include=*.js | grep -v lib/ | grep -v '\.test\.js'`
Expected: 僅剩少數合法的 entry 級 DOM 黏合(per-page select、`mountIsland` 前的讀取),無大段命令式 UI 邏輯殘留。

- [ ] **Step 4: Commit**
```bash
git commit -m "chore(js): Vue 島嶼遷移收尾與全量驗證"
```

---

## Self-Review

- **Spec coverage(對照評估報告四批):** M0 build 基建 ✓;M1 試點(searchable-select、grades/form)✓;M2 中小型 + deleteModal/flash 共用化 + 契約 class 處理 ✓;M3 大型表單(bills/create、bills/index)✓;M4 聊天頁 + bootstrap Echo/badge 收斂 ✓。18 支來源檔皆有對應任務(app.js 僅 `import './bootstrap'` 不需改)。
- **Placeholder scan:** M0/M1/M2 共用件給出完整程式碼與測試;M3/M4 大型元件給出 props/state 介面、純函式完整實作與測試、關鍵行為測試、entry/Blade 接線完整,並以「逐項對照來源行為 + 來源行號」取代逐行貼上(來源即行為規格,存在於 repo,非 TBD)。
- **Type consistency:** 共用件命名一致 — `mountIsland(mountId, Component, extraProps)`、`http`(default export)、`useFlash().showFlash/autoDismissFlashes`、`useConfirmModal()→{open,busy,target,show,close}`、`ConfirmModal` props `open/title/name/actionLabel/busy` + emits `confirm/cancel`、`useChatBadge().refresh()`、`useEcho().echo`。各島 entry 一律 `mountIsland('<id>', Component[, extraProps])`。
- **風險備註:** bills/create 與 chats 為高風險大件,務必以雙人 / 真實環境人工回歸對照舊版行為;每島可獨立回退(git revert 該島 commit),不影響其他頁。
