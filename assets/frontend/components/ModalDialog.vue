<script setup>
import { onMounted, onUnmounted } from 'vue';

const props = defineProps({
  maxWidthClass: { type: String, default: 'max-w-2xl' },
});

const emit = defineEmits(['close']);

const handleWindowKeydown = (event) => {
  if (event.key !== 'Escape') {
    return;
  }

  event.preventDefault();
  emit('close');
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
    @click.self="emit('close')"
  >
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-2xl" :class="maxWidthClass">
      <slot />
    </div>
  </div>
</template>
