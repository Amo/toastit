<script setup>
const props = defineProps({
  label: { type: String, required: true },
  toneClass: { type: String, default: 'text-stone-500' },
});
</script>

<template>
  <span class="inline-flex items-center gap-2">
    <i class="fa-regular fa-circle-dot" :class="toneClass" aria-hidden="true"></i>
    <span class="font-semibold" :class="toneClass">{{ label }}</span>
  </span>
</template>
