<script setup>
const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
});
</script>

<template>
  <div class="space-y-2">
    <p v-if="eyebrow" class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">{{ eyebrow }}</p>
    <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ title }}</h1>
    <p v-if="description" class="text-base leading-7 text-stone-600">{{ description }}</p>
  </div>
</template>
