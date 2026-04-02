<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';

const props = defineProps({
  success: { type: Array, default: () => [] },
  error: { type: Array, default: () => [] },
});

const emit = defineEmits(['dismiss']);

let clearTimerId = null;

const clearTimer = () => {
  if (null !== clearTimerId) {
    window.clearTimeout(clearTimerId);
    clearTimerId = null;
  }
};

const scheduleAutoClear = () => {
  clearTimer();

  if (!props.success.length && !props.error.length) {
    return;
  }

  clearTimerId = window.setTimeout(() => {
    [...props.success].forEach(() => emit('dismiss', { type: 'success', index: 0 }));
    [...props.error].forEach(() => emit('dismiss', { type: 'error', index: 0 }));
  }, 6000);
};

watch(() => [props.success.length, props.error.length], scheduleAutoClear);

onMounted(scheduleAutoClear);
onBeforeUnmount(clearTimer);
</script>

<template>
  <div v-if="success.length || error.length" class="space-y-3">
    <div
      v-for="(message, index) in success"
      :key="`success-${index}`"
      class="flex items-start justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    >
      <p class="min-w-0 flex-1">{{ message }}</p>
      <button type="button" class="shrink-0 text-emerald-500 transition hover:text-emerald-700" @click="emit('dismiss', { type: 'success', index })">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div
      v-for="(message, index) in error"
      :key="`error-${index}`"
      class="flex items-start justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      <p class="min-w-0 flex-1">{{ message }}</p>
      <button type="button" class="shrink-0 text-rose-500 transition hover:text-rose-700" @click="emit('dismiss', { type: 'error', index })">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
  </div>
</template>
