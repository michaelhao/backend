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
    const val = String(weight.value).trim();
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
