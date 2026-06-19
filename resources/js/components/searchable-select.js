import { createApp } from 'vue';
import SearchableSelect from './SearchableSelect.vue';
import { readProps } from '@/lib/mountIsland';

document.querySelectorAll('.ss-island').forEach((el) => {
    createApp(SearchableSelect, readProps(el)).mount(el);
});
