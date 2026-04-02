<script setup>
const props = defineProps({
  type: { type: String, default: 'button' },
  href: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
});
</script>

<template>
  <a
    v-if="href"
    class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400"
    :href="href"
  >
    <slot />
  </a>
  <button
    v-else
    class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60"
    :type="type"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>
