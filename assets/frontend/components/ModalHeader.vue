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
  <div class="relative border-b border-stone-100 px-4 py-4 md:px-6 md:py-5 md:pr-20">
    <div class="mb-3 md:hidden">
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-full border border-stone-200 px-3 py-1.5 text-sm font-medium text-stone-600 transition hover:border-stone-300 hover:text-stone-800"
        @click="$emit('close')"
      >
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        <span>Back</span>
      </button>
    </div>
    <div>
      <div v-if="eyebrow || $slots.eyebrow" class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <EyebrowLabel v-if="eyebrow">{{ eyebrow }}</EyebrowLabel>
        <slot name="eyebrow" />
      </div>
      <h2 class="mt-2 text-2xl font-semibold text-stone-950">{{ title }}</h2>
      <p v-if="description" class="mt-2 text-sm text-stone-500">{{ description }}</p>
      <slot />
    </div>
    <button type="button" class="absolute right-6 top-5 hidden h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800 md:inline-grid" @click="$emit('close')">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      <span class="sr-only">Close modal</span>
    </button>
  </div>
</template>
