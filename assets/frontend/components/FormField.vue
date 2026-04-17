<script setup>
const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
  hideLabel: { type: Boolean, default: false },
});
</script>

<template>
  <label class="grid gap-2 text-sm font-medium text-stone-700">
    <span v-if="!hideLabel">{{ label }}</span>
    <slot />
    <span v-if="help" class="text-xs text-stone-400">{{ help }}</span>
  </label>
</template>
