<script setup>
const props = defineProps({
  label: { type: String, required: true },
  toneClass: { type: String, default: 'text-stone-500' },
  badgeClass: { type: String, default: 'border-stone-200 bg-white' },
});
</script>

<template>
  <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="badgeClass">
    <i class="fa-regular fa-circle-dot" :class="toneClass" aria-hidden="true"></i>
    <span :class="toneClass">{{ label }}</span>
  </span>
</template>
