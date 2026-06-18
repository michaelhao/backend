import { createApp } from 'vue';
import SearchableSelect from './SearchableSelect.vue';

document.querySelectorAll('.ss-island').forEach((el) => {
    let props = {};
    try { props = JSON.parse(el.dataset.props); } catch { props = {}; }
    createApp(SearchableSelect, props).mount(el);
});
