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
    class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
    :href="href"
  >
    <slot />
  </a>
  <button
    v-else
    class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60"
    :type="type"
    :disabled="disabled"
  >
    <slot />
  </button>
</template>
