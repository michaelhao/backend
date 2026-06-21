<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';

const props = defineProps({
    lifetime: { type: Number, required: true },
    loginUrl: { type: String, required: true },
});

const remaining = ref(props.lifetime);
let timer = null;

function pad(n) {
    return String(n).padStart(2, '0');
}

const formatted = computed(() => {
    const h = Math.floor(remaining.value / 3600);
    const m = Math.floor((remaining.value % 3600) / 60);
    const s = remaining.value % 60;
    return `${pad(h)}:${pad(m)}:${pad(s)}`;
});

const isWarning = computed(() => remaining.value <= 300);

onMounted(() => {
    timer = setInterval(() => {
        remaining.value--;
        if (remaining.value <= 0) {
            clearInterval(timer);
            window.location.href = props.loginUrl;
        }
    }, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});
</script>

<template>
  <span
    class="text-sm font-mono"
    :class="isWarning ? 'text-red-500' : 'text-slate-400'"
    title="Session 剩餘時間"
  >{{ formatted }}</span>
</template>
