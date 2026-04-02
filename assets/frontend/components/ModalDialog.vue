<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
  maxWidthClass: { type: String, default: 'max-w-2xl' },
});

const emit = defineEmits(['close']);
const pointerStartedOnBackdrop = ref(false);

const handleWindowKeydown = (event) => {
  if (event.key !== 'Escape') {
    return;
  }

  event.preventDefault();
  emit('close');
};

const handleBackdropPointerDown = () => {
  pointerStartedOnBackdrop.value = true;
};

const handleBackdropPointerUp = () => {
  if (pointerStartedOnBackdrop.value) {
    emit('close');
  }

  pointerStartedOnBackdrop.value = false;
};

const resetBackdropPointerState = () => {
  pointerStartedOnBackdrop.value = false;
};

onMounted(() => {
  window.addEventListener('keydown', handleWindowKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleWindowKeydown);
});
</script>

<template>
  <div
    class="!mt-0 fixed inset-0 z-[70] flex items-center justify-center bg-stone-950/20 px-4 py-[5vh] backdrop-blur-[9px]"
    @pointerdown.self="handleBackdropPointerDown"
    @pointerup.self="handleBackdropPointerUp"
    @pointerleave="resetBackdropPointerState"
    @pointercancel="resetBackdropPointerState"
  >
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-2xl" :class="maxWidthClass">
      <slot />
    </div>
  </div>
</template>
