<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const model = defineModel({ default: '' });

const props = defineProps({
  options: { type: Array, default: () => [] },
  icon: { type: String, default: '' },
});

const open = ref(false);
const root = ref(null);

const selectedOptionLabel = () => props.options.find((option) => option.value === model.value)?.label ?? '';

const closeOnOutsideClick = (event) => {
  if (!root.value || root.value.contains(event.target)) {
    return;
  }

  open.value = false;
};

const selectOption = (value) => {
  model.value = value;
  open.value = false;
};

onMounted(() => {
  window.addEventListener('click', closeOnOutsideClick);
});

onUnmounted(() => {
  window.removeEventListener('click', closeOnOutsideClick);
});
</script>

<template>
  <div ref="root" class="relative inline-flex min-w-[11rem] flex-col">
    <button
      type="button"
      class="inline-flex items-center justify-between gap-3 rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
      @click="open = !open"
    >
      <span class="inline-flex items-center gap-2">
        <i v-if="icon" :class="icon" class="text-sm text-stone-400" aria-hidden="true"></i>
        <span>{{ selectedOptionLabel() }}</span>
      </span>
      <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-[calc(100%+0.5rem)] z-20 min-w-full overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-stone-200/60"
    >
      <button
        v-for="option in options"
        :key="option.value || option.label"
        type="button"
        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-stone-700 transition hover:bg-stone-50"
        :class="model === option.value ? 'bg-amber-50 text-amber-800' : ''"
        @click="selectOption(option.value)"
      >
        <span>{{ option.label }}</span>
        <i v-if="model === option.value" class="fa-solid fa-check text-xs" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</template>
