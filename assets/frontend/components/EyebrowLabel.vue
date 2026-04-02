<script setup>
const props = defineProps({
  toneClass: { type: String, default: 'text-amber-600' },
});
</script>

<template>
  <p class="text-xs font-semibold uppercase tracking-[0.22em]" :class="toneClass">
    <slot />
  </p>
</template>
