<script setup>
import KeyboardHint from './KeyboardHint.vue';

defineProps({
  canNavigatePrevious: { type: Boolean, required: true },
  canNavigateNext: { type: Boolean, required: true },
});

defineEmits(['previous', 'next']);
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-stone-100 pt-4">
    <div class="inline-flex items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-medium text-stone-600 transition hover:border-stone-300 hover:text-stone-900 disabled:opacity-40"
        :disabled="!canNavigatePrevious"
        @click="$emit('previous')"
      >
        <i class="fa-solid fa-arrow-left text-[0.65rem]" aria-hidden="true"></i>
        <span>Previous</span>
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-1 rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-medium text-stone-600 transition hover:border-stone-300 hover:text-stone-900 disabled:opacity-40"
        :disabled="!canNavigateNext"
        @click="$emit('next')"
      >
        <span>Next</span>
        <i class="fa-solid fa-arrow-right text-[0.65rem]" aria-hidden="true"></i>
      </button>
    </div>
    <KeyboardHint>Use keyboard arrows to move between toasts.</KeyboardHint>
  </div>
</template>
