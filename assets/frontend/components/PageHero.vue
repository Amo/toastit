<script setup>
import EyebrowLabel from './EyebrowLabel.vue';

const props = defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
});
</script>

<template>
  <div class="space-y-2">
    <EyebrowLabel v-if="eyebrow">{{ eyebrow }}</EyebrowLabel>
    <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ title }}</h1>
    <p v-if="description" class="text-base leading-7 text-stone-600">{{ description }}</p>
  </div>
</template>
