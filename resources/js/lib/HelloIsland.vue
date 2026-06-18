<script setup>
defineProps({ name: { type: String, default: 'island' } });
</script>

<template>
  <p>Hello {{ name }}</p>
</template>
