<script setup>
const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
});
</script>

<template>
  <label class="grid gap-2 text-sm font-medium text-stone-700">
    <span>{{ label }}</span>
    <slot />
    <span v-if="help" class="text-xs text-stone-400">{{ help }}</span>
  </label>
</template>
