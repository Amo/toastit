<script setup>
import EyebrowLabel from './EyebrowLabel.vue';

defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  description: { type: String, default: '' },
});

defineEmits(['close']);
</script>

<template>
  <div class="flex items-start justify-between gap-4 border-b border-stone-100 px-6 py-5">
    <div>
      <EyebrowLabel v-if="eyebrow">{{ eyebrow }}</EyebrowLabel>
      <h2 class="mt-2 text-2xl font-semibold text-stone-950">{{ title }}</h2>
      <p v-if="description" class="mt-2 text-sm text-stone-500">{{ description }}</p>
      <slot />
    </div>
    <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800" @click="$emit('close')">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      <span class="sr-only">Close modal</span>
    </button>
  </div>
</template>
