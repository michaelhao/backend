import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SearchableSelect from '@/components/SearchableSelect.vue';

const groups = [
    { module: '使用者', options: [
        { value: 'User.index', label: '使用者 - 列表', action: '列表', search: '使用者 列表 user.index' },
        { value: 'User.create', label: '使用者 - 新增', action: '新增', search: '使用者 新增 user.create' },
    ] },
    { module: '角色', options: [
        { value: 'Role.index', label: '角色 - 列表', action: '列表', search: '角色 列表 role.index' },
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
        expect(visible[0].text()).toBe('列表');
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
