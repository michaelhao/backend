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
