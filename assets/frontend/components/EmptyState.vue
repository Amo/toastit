<script setup>
defineProps({
  message: { type: String, required: true },
  className: { type: String, default: '' },
});
</script>

<template>
  <div class="text-sm text-stone-500" :class="className">{{ message }}</div>
</template>
