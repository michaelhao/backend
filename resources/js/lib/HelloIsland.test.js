import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import HelloIsland from '@/lib/HelloIsland.vue';

describe('HelloIsland', () => {
    it('渲染傳入的 name', () => {
        const wrapper = mount(HelloIsland, { props: { name: '世界' } });
        expect(wrapper.text()).toContain('Hello 世界');
    });
});
