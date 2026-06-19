<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

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

const rootEl = ref(null);
const onDocClick = (e) => {
    if (rootEl.value && !rootEl.value.contains(e.target)) { onBlurClose(); }
};

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
  <div ref="rootEl" class="relative">
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
      <div v-for="g in visibleGroups" :key="g.module" class="ss-group">
        <div class="px-3 py-1.5 text-xs font-semibold text-gray-500 uppercase bg-gray-50 sticky top-0">{{ g.module }}</div>
        <button
          v-for="o in g.options"
          :key="o.value"
          type="button"
          class="w-full text-left px-3 py-2 pl-6 text-sm text-gray-700 hover:bg-blue-50 transition-colors ss-option"
          :class="{ 'bg-blue-50 text-blue-700 font-medium': selected === o.value }"
          @click="choose(o)"
        >{{ o.action }}</button>
      </div>
      <div v-show="!hasResults" class="px-3 py-2 text-sm text-gray-400 ss-no-results">無符合結果</div>
    </div>
  </div>
</template>
