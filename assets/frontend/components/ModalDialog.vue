<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
  maxWidthClass: { type: String, default: 'max-w-2xl' },
  zIndexClass: { type: String, default: 'z-[70]' },
  desktopInline: { type: Boolean, default: false },
  backdropClosable: { type: Boolean, default: true },
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
  if (!props.backdropClosable) {
    return;
  }

  pointerStartedOnBackdrop.value = true;
};

const handleBackdropPointerUp = () => {
  if (!props.backdropClosable) {
    return;
  }

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
    class="!mt-0 bg-white"
    :class="[
      zIndexClass,
      desktopInline
        ? 'fixed inset-0 md:static md:block md:bg-transparent md:px-0 md:py-0 md:backdrop-blur-0'
        : 'fixed inset-0 md:flex md:items-center md:justify-center md:bg-stone-950/20 md:px-4 md:py-[5vh] md:backdrop-blur-[9px]',
    ]"
    @pointerdown.self="handleBackdropPointerDown"
    @pointerup.self="handleBackdropPointerUp"
    @pointerleave="resetBackdropPointerState"
    @pointercancel="resetBackdropPointerState"
  >
    <div
      class="flex h-full w-full flex-col overflow-hidden bg-white"
      :class="[
        maxWidthClass,
        desktopInline
          ? 'md:h-auto md:max-h-none md:rounded-[1.75rem] md:border md:border-stone-200 md:shadow-sm'
          : 'md:h-auto md:max-h-[90vh] md:rounded-[1.75rem] md:shadow-2xl',
      ]"
    >
      <slot />
    </div>
  </div>
</template>
