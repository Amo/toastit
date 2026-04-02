<script setup>
const props = defineProps({
  success: { type: Array, default: () => [] },
  error: { type: Array, default: () => [] },
});
</script>

<template>
  <div v-if="success.length || error.length" class="space-y-3">
    <p
      v-for="(message, index) in success"
      :key="`success-${index}`"
      class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
    >
      {{ message }}
    </p>
    <p
      v-for="(message, index) in error"
      :key="`error-${index}`"
      class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
    >
      {{ message }}
    </p>
  </div>
</template>
